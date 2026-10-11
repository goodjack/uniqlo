<?php

namespace App\Services;

use App\Enums\CrawlOutcome;
use App\Repositories\JapanProductRepository;
use App\Services\Traits\AntiBlockingCrawler;
use App\Support\CatalogScan;
use App\Support\CrawlResult;
use App\Support\StockoutGate;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class JapanProductService
{
    use AntiBlockingCrawler;

    private const CACHE_KEY_JAPAN_PRODUCTS_OFFSET = 'japan_products:offset:%s'; // brand

    public function __construct(protected JapanProductRepository $repository) {}

    /** 缺貨判定的前提與台灣那支相同（CatalogScan、StockoutGate） */
    public function fetchAllProducts($brand = 'UNIQLO', bool $fresh = false, bool $acceptShrink = false): CrawlResult
    {
        $japanProductListApiUrl = $this->getJapanProductListApiUrl($brand);
        $cacheKey = sprintf(self::CACHE_KEY_JAPAN_PRODUCTS_OFFSET, $brand);

        $limit = (int) config('app.crawler.page_sizes.japan_products');
        if ($limit < 1) {
            throw new Exception('CRAWLER_JAPAN_PRODUCTS_PAGE_SIZE is not configured.');
        }

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $startOffset = (int) ($fresh ? 0 : (Cache::get($cacheKey) ?? 0));
        $offset = $startOffset;
        $total = 0;
        $hasSucceeded = false;
        $scan = new CatalogScan($limit);
        $gate = new StockoutGate('japan', $brand);
        $retryTimes = (int) config('app.crawler.retry.times');
        $failedIds = [];
        $unidentifiedFailures = 0;

        logger()->info("Fetching Japan products for {$brand}, starting from offset {$offset}");

        do {
            try {
                // retry 只包打官網與解析回傳：資料庫的問題重打官網修不好
                [$items, $total] = retry(
                    $retryTimes,
                    function ($attempts) use ($japanProductListApiUrl, $brand, $offset, $limit, $scan, $retryTimes) {
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

                        $total = (int) $responseBody->result->pagination->total;

                        // 200 加空清單常是一時的軟擋，先重打；最後一次仍不足才照實記成缺口
                        if ($attempts < $retryTimes && count($items) < $scan->expectedCount($offset, $total)) {
                            throw new Exception("Batch at offset {$offset} came back short");
                        }

                        return [$items, $total];
                    },
                    fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                    fn ($e) => $this->shouldRetry($e),
                );

                $hasSucceeded = true;

                if (! $scan->recordBatch($offset, count($items), $total)) {
                    logger()->error('JapanProductService fetchAllProducts batch came back short', [
                        'brand' => $brand,
                        'offset' => $offset,
                        'item_count' => count($items),
                        'total' => $total,
                    ]);
                }

                $saveResult = $this->repository->saveProducts($items, $brand);

                if ($saveResult->hasFailures()) {
                    $failedIds = array_merge($failedIds, $saveResult->failedProductCodes);
                    $unidentifiedFailures += $saveResult->unidentifiedFailureCount;
                }

                $offset += $limit;

                $this->saveCheckpoint($cacheKey, $offset);

                if ($this->shouldOffsetBatchRest($offset, $limit)) {
                    $this->doOffsetBatchRest();
                }

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 是封鎖，再打只會更糟，整輪停下
                if ($this->is403Error($e)) {
                    logger()->error('fetchAllProducts blocked (403)', [
                        'brand' => $brand,
                        'offset' => $offset,
                    ]);
                    report($e);

                    $earlierGapPage = $scan->hasGap() ? $scan->pageOf($scan->firstGap()) : null;
                    $scan->recordGap($offset);
                    $this->saveCheckpoint($cacheKey, $scan->firstGap());

                    if (! $hasSucceeded) {
                        return new CrawlResult(CrawlOutcome::Failed);
                    }

                    $note = "未執行缺貨判定，第 {$scan->pageOf($offset)} 頁起被擋下（403）";

                    return $gate->skip(
                        $earlierGapPage === null ? $note : "{$note}，更早在第 {$earlierGapPage} 頁就有缺頁"
                    );
                }

                $scan->recordGap($offset);
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
        } while ($total > $offset);

        $scan->recordEnd($offset);

        logger()->info('JapanProductService fetchAllProducts catalog summary', [
            'brand' => $brand,
            'largest_total' => $scan->largestTotal(),
            'items_seen' => $scan->itemsSeen(),
        ]);

        if (! $hasSucceeded) {
            logger()->error('No batches were successfully fetched - preserving checkpoint', ['brand' => $brand]);

            return new CrawlResult(CrawlOutcome::Failed);
        }

        if ($scan->hasGap()) {
            logger()->error('The catalog has gaps - rewinding checkpoint, skipping stockout', [
                'brand' => $brand,
                'first_gap_offset' => $scan->firstGap(),
            ]);

            $this->saveCheckpoint($cacheKey, $scan->firstGap());

            return $gate->skip("未執行缺貨判定，目錄有缺頁（最早在第 {$scan->pageOf($scan->firstGap())} 頁）");
        }

        Cache::forget($cacheKey);

        $result = $gate->decide(
            $scan,
            $startOffset !== 0,
            $failedIds,
            $unidentifiedFailures,
            fn () => $this->repository->countInStockProducts($brand),
            $acceptShrink,
            fn (array $excluded) => $this->repository->setStockoutProducts($brand, null, $excluded),
        );

        if ($result->outcome === CrawlOutcome::Succeeded) {
            logger()->info("Completed fetching Japan products for {$brand}");
        }

        return $result;
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
