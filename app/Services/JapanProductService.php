<?php

namespace App\Services;

use App\Repositories\JapanProductRepository;
use App\Services\Traits\AntiBlockingCrawler;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class JapanProductService
{
    use AntiBlockingCrawler;

    private const CACHE_KEY_JAPAN_PRODUCTS_OFFSET = 'japan_products:offset:%s'; // brand

    public function __construct(protected JapanProductRepository $repository)
    {
    }

    public function fetchAllProducts($brand = 'UNIQLO', bool $fresh = false): bool
    {
        $japanProductListApiUrl = $this->getJapanProductListApiUrl($brand);
        $cacheKey = sprintf(self::CACHE_KEY_JAPAN_PRODUCTS_OFFSET, $brand);

        $limit = (int) config('app.crawler.page_sizes.japan_products');
        if ($limit < 1) {
            throw new Exception('CRAWLER_JAPAN_PRODUCTS_PAGE_SIZE is not configured.');
        }

        $offset = $fresh ? 0 : (Cache::get($cacheKey) ?? 0);
        $total = 0;
        $hasSucceeded = false;
        $hasFailures = false;

        // Clear checkpoint if fresh
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        logger()->info("Fetching Japan products for {$brand}, starting from offset {$offset}");

        do {
            try {
                $total = retry(
                    config('app.crawler.retry.times'),
                    function ($attempts) use ($japanProductListApiUrl, $brand, $offset, $limit) {
                        $headers = $this->buildHeaders();
                        $headers['x-fr-clientid'] = $this->getClientId($brand);

                        $response = Http::withHeaders($headers)
                            ->throw()
                            ->get($japanProductListApiUrl, [
                                'offset' => $offset,
                                'limit' => $limit,
                                'sort' => 1,
                                'httpFailure' => 'true',
                                'queryRelaxationFlag' => 'true',
                            ]);

                        $responseBody = json_decode($response->body());
                        $items = $responseBody->result->items ?? null;

                        if (is_null($items)) {
                            throw new Exception("Items does not exist. {$response->body()}");
                        }

                        $this->repository->saveProducts($items, $brand);

                        return $responseBody->result->pagination->total;
                    },
                    fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                    fn ($e) => $this->shouldRetry($e),
                );

                $hasSucceeded = true;

                if ($total === 0) {
                    logger()->info("No products found for {$brand}, stopping.");
                    break;
                }

                $offset += $limit;

                // Update checkpoint
                Cache::put($cacheKey, $offset, now()->addDays(7));

                // Check if offset batch rest is needed
                if ($this->shouldOffsetBatchRest($offset, $limit)) {
                    $this->doOffsetBatchRest();
                }

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 is a permanent block - stop immediately
                if ($this->is403Error($e)) {
                    logger()->error('fetchAllProducts blocked (403)', [
                        'brand' => $brand,
                        'offset' => $offset,
                    ]);
                    report($e);

                    return false;
                }

                // retry() exhausted - skip batch and continue
                $hasFailures = true;
                logger()->error('JapanProductService fetchAllProducts error - max retry exceeded', [
                    'brand' => $brand,
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total,
                    'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                    'error' => $e->getMessage(),
                ]);
                report($e);

                $offset += $limit;
            }
        } while ($total >= $offset);

        if ($hasSucceeded && ! $hasFailures) {
            // All batches succeeded - clear checkpoint and run stockout processing
            Cache::forget($cacheKey);
            $this->repository->setStockoutProducts($brand);
            logger()->info("Completed fetching Japan products for {$brand}");
        } elseif ($hasSucceeded) {
            // Partial success - preserve checkpoint, skip stockout to avoid false negatives
            logger()->warning('Some batches failed - preserving checkpoint, skipping stockout', ['brand' => $brand]);
        } else {
            logger()->warning('No batches were successfully fetched - preserving checkpoint', ['brand' => $brand]);
        }

        return true;
    }

    private function getJapanProductListApiUrl($brand = 'UNIQLO')
    {
        if ($brand === 'GU') {
            return config('gu.api.product_list.jp');
        }

        return config('uniqlo.api.product_list.jp');
    }

    private function getClientId($brand = 'UNIQLO')
    {
        if ($brand === 'GU') {
            return 'gu.jp.web-mem-cnc';
        }

        return 'uq.jp.web-spa';
    }
}
