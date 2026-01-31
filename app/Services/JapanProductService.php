<?php

namespace App\Services;

use App\Repositories\JapanProductRepository;
use App\Services\Traits\AntiBlockingCrawler;
use Exception;
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

    public function fetchAllProducts($brand = 'UNIQLO', bool $fresh = false): void
    {
        $japanProductListApiUrl = $this->getJapanProductListApiUrl($brand);
        $cacheKey = sprintf(self::CACHE_KEY_JAPAN_PRODUCTS_OFFSET, $brand);

        $limit = 36;
        $offset = $fresh ? 0 : (Cache::get($cacheKey) ?? 0);
        $total = 0;
        $retry = 0;
        $maxRetry = config('app.crawler.retry.manual');

        // Clear checkpoint if fresh
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        logger()->info("Fetching Japan products for {$brand}, starting from offset {$offset}");

        do {
            try {
                $headers = $this->buildHeaders();
                $headers['x-fr-clientid'] = $this->getClientId($brand);

                $response = Http::withHeaders($headers)
                    ->retry($maxRetry, 1000)
                    ->get($japanProductListApiUrl, [
                        'offset' => $offset,
                        'limit' => $limit,
                        'sort' => 1,
                        'httpFailure' => 'true',
                        'queryRelaxationFlag' => 'true',
                    ]);

                $responseBody = json_decode($response->body());
                $items = $responseBody->result->items;

                $this->repository->saveProducts($items, $brand);

                $total = $responseBody->result->pagination->total;

                if ($total === 0) {
                    throw new Exception('No products found');
                }

                $offset += $limit;

                // Update checkpoint
                Cache::set($cacheKey, $offset, now()->addDays(7));

                $retry = 0;

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

                    return;
                }

                if ($retry >= $maxRetry) {
                    logger()->error('JapanProductService fetchAllProducts error - max retry exceeded', [
                        'brand' => $brand,
                        'retry' => $retry,
                        'limit' => $limit,
                        'offset' => $offset,
                        'total' => $total,
                        'status_code' => $e instanceof \Illuminate\Http\Client\RequestException ? $e->response?->status() : 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    report($e);

                    $offset += $limit;
                    $retry = 0;

                    continue;
                }

                $retry++;

                $this->randomSleep();
            }
        } while ($total >= $offset);

        // Clear checkpoint on complete success
        Cache::forget($cacheKey);

        $this->repository->setStockoutProducts($brand);

        logger()->info("Completed fetching Japan products for {$brand}");
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
