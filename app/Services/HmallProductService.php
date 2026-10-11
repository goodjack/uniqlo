<?php

namespace App\Services;

use App\Enums\CrawlOutcome;
use App\Models\HmallProduct;
use App\Presenters\HmallProductPresenter;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\Traits\AntiBlockingCrawler;
use App\Support\CatalogScan;
use App\Support\CrawlResult;
use App\Support\StockoutGate;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class HmallProductService extends Service
{
    use AntiBlockingCrawler;

    /** @var HmallProductRepository */
    protected $repository;

    protected $productRepository;

    private const CACHE_KEY_HMALL_PRODUCTS_PAGE = 'hmall_products:page:%s';

    private const CACHE_KEY_HMALL_DESCRIPTIONS = 'hmall_descriptions:last_id:%s';

    private const CACHE_KEY_HMALL_DESCRIPTION_REFETCH = 'hmall_descriptions:refetch_below_id:%s';

    public function __construct(HmallProductRepository $repository, ProductRepository $productRepository)
    {
        $this->repository = $repository;
        $this->productRepository = $productRepository;
    }

    public function getRelatedHmallProducts(HmallProduct $hmallProduct)
    {
        return $this->repository->getRelatedHmallProducts($hmallProduct);
    }

    public function getRelatedProducts(HmallProduct $hmallProduct)
    {
        return $this->productRepository->getRelatedProductsForHmallProduct($hmallProduct);
    }

    public function getCommonlyStyledHmallProducts(HmallProduct $hmallProduct, int $limit = 6)
    {
        return $this->repository->getCommonlyStyledHmallProducts($hmallProduct, $limit);
    }

    public function getStyles(HmallProduct $hmallProduct)
    {
        return $this->repository->getStyles($hmallProduct);
    }

    public function getStyleHints(HmallProduct $hmallProduct, int $limit)
    {
        return $this->repository->getStyleHints($hmallProduct, $limit);
    }

    public function getStyleHintCount(HmallProduct $hmallProduct)
    {
        return $this->repository->getStyleHintCount($hmallProduct);
    }

    /**
     * 缺貨判定是把 updated_at 早於今天的商品標成下架，只有這一輪確定看完整份目錄
     * 才能做（CatalogScan、StockoutGate）；個別商品寫不進去時照做但排除那幾件。
     */
    public function fetchAllHmallProducts($brand = 'UNIQLO', bool $fresh = false, bool $acceptShrink = false): CrawlResult
    {
        $searchApiUrl = $this->getV3SearchApiUrl($brand);

        $pageSize = (int) config('app.crawler.page_sizes.hmall_products');
        if ($pageSize < 1) {
            throw new Exception('CRAWLER_HMALL_PRODUCTS_PAGE_SIZE is not configured.');
        }

        $cacheKey = sprintf(self::CACHE_KEY_HMALL_PRODUCTS_PAGE, $brand);

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $startPage = (int) ($fresh ? 1 : (Cache::get($cacheKey) ?? 1));
        $startedFromFirstPage = $startPage === 1;

        $page = $startPage;
        $productSum = 0;
        $hasSucceeded = false;
        $scan = new CatalogScan($pageSize);
        $gate = new StockoutGate('hmall', $brand);
        $retryTimes = (int) config('app.crawler.retry.times');
        $failedProductCodes = [];
        $unidentifiedFailures = 0;

        logger()->info("Fetching Hmall products for {$brand}, starting from page {$page}");

        do {
            try {
                // retry 只包打官網與解析回傳：資料庫的問題重打官網修不好
                [$products, $productSum] = retry(
                    $retryTimes,
                    function ($attempts) use ($searchApiUrl, $page, $pageSize, $scan, $retryTimes) {
                        $response = Http::withHeaders($this->buildHeaders())
                            ->throw()
                            ->post($searchApiUrl, [
                                'belongTo' => 'h5',
                                'pageInfo' => [
                                    'page' => $page,
                                    'pageSize' => $pageSize,
                                ],
                                'description' => '',
                                'priceRange' => (object) [],
                                'size' => [],
                                'color' => [],
                                'stockFilter' => 'warehouse',
                                'identity' => [],
                                'rank' => 'overall',
                            ]);

                        $responseBody = json_decode($response->getBody());
                        $products = $responseBody->resp[0]->productList ?? null;

                        if (is_null($products)) {
                            throw new Exception("Product list does not exist. {$response->body()}");
                        }

                        $productSum = (int) $responseBody->resp[0]->productSum;

                        // 200 加空清單常是一時的軟擋，先重打；最後一次仍不足才照實記成缺口
                        if (
                            $attempts < $retryTimes
                            && count($products) < $scan->expectedCount(($page - 1) * $pageSize, $productSum)
                        ) {
                            throw new Exception("Page {$page} came back short");
                        }

                        return [$products, $productSum];
                    },
                    fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                    fn ($e) => $this->shouldRetry($e),
                );

                $productCount = count($products);

                if (! $scan->recordBatch(($page - 1) * $pageSize, $productCount, $productSum)) {
                    logger()->error('fetchAllHmallProducts page came back short', [
                        'brand' => $brand,
                        'page' => $page,
                        'product_count' => $productCount,
                        'productSum' => $productSum,
                    ]);
                }

                $saveResult = $this->repository->saveProductsFromV3($products, $brand);

                if ($saveResult->hasFailures()) {
                    $failedProductCodes = array_merge(
                        $failedProductCodes,
                        $saveResult->failedProductCodes
                    );
                    $unidentifiedFailures += $saveResult->unidentifiedFailureCount;

                    logger()->error('Some products on this page could not be saved', [
                        'brand' => $brand,
                        'page' => $page,
                        'failed_product_codes' => $saveResult->failedProductCodes,
                        'unidentified_failures' => $saveResult->unidentifiedFailureCount,
                    ]);
                }

                $hasSucceeded = true;

                $this->saveCheckpoint($cacheKey, $page + 1);

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 是封鎖，再打只會更糟，整輪停下
                if ($this->is403Error($e)) {
                    logger()->error('fetchAllHmallProducts blocked (403)', [
                        'brand' => $brand,
                        'page' => $page,
                        'pageSize' => $pageSize,
                    ]);
                    report($e);

                    $earlierGapPage = $scan->hasGap() ? $scan->pageOf($scan->firstGap()) : null;
                    $scan->recordGap(($page - 1) * $pageSize);
                    $this->saveCheckpoint($cacheKey, $scan->pageOf($scan->firstGap()));

                    if (! $hasSucceeded) {
                        return new CrawlResult(CrawlOutcome::Failed);
                    }

                    // 前面幾頁已經寫進去了，不能說成完全失敗
                    $note = "未執行缺貨判定，第 {$page} 頁起被擋下（403）";

                    return $gate->skip(
                        $earlierGapPage === null ? $note : "{$note}，更早在第 {$earlierGapPage} 頁就有缺頁"
                    );
                }

                $scan->recordGap(($page - 1) * $pageSize);
                logger()->error('fetchAllHmallProducts error - max retry exceeded', [
                    'brand' => $brand,
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'productSum' => $productSum,
                    'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }

            $page++;
        } while ($productSum > ($page - 1) * $pageSize);

        $scan->recordEnd(($page - 1) * $pageSize);

        logger()->info('fetchAllHmallProducts catalog summary', [
            'brand' => $brand,
            'largest_product_sum' => $scan->largestTotal(),
            'items_seen' => $scan->itemsSeen(),
        ]);

        if (! $hasSucceeded) {
            logger()->error('No pages were successfully fetched - preserving checkpoint', ['brand' => $brand]);

            return new CrawlResult(CrawlOutcome::Failed);
        }

        if ($scan->hasGap()) {
            $firstGapPage = $scan->pageOf($scan->firstGap());

            logger()->error('The catalog has gaps - rewinding checkpoint, skipping stockout', [
                'brand' => $brand,
                'first_gap_page' => $firstGapPage,
            ]);

            $this->saveCheckpoint($cacheKey, $firstGapPage);

            return $gate->skip("未執行缺貨判定，目錄有缺頁（最早在第 {$firstGapPage} 頁）");
        }

        Cache::forget($cacheKey);

        $result = $gate->decide(
            scan: $scan,
            resumed: ! $startedFromFirstPage,
            failedIds: $failedProductCodes,
            unidentifiedFailures: $unidentifiedFailures,
            countInStock: fn () => $this->repository->countInStockHmallProducts($brand),
            acceptShrink: $acceptShrink,
            markStockout: fn (array $excluded) => $this->repository->setStockoutHmallProducts($brand, null, $excluded),
        );

        if ($result->outcome === CrawlOutcome::Succeeded) {
            logger()->info("Completed fetching Hmall products for {$brand}");
        }

        return $result;
    }

    public function fetchAllHmallProductDescriptions(string $brand = 'UNIQLO', bool $updateTimestamps = false, bool $fresh = false): bool
    {
        $cacheKey = sprintf(self::CACHE_KEY_HMALL_DESCRIPTIONS, $brand);

        // Clear checkpoint if fresh
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $lastProcessedId = $fresh ? null : Cache::get($cacheKey);

        $query = HmallProduct::whereNull('instruction')
            ->where('brand', $brand)
            ->select(['id', 'product_code']);

        if ($lastProcessedId) {
            $query->where('id', '<', $lastProcessedId);
            logger()->info("Resuming Hmall product descriptions for {$brand} from ID {$lastProcessedId}");
        } else {
            logger()->info("Fetching Hmall product descriptions for {$brand} from start");
        }

        $hmallProducts = $query->lazyByIdDesc();

        $this->resetDetailCounter();

        $hasSucceeded = false;

        foreach ($hmallProducts as $hmallProduct) {
            try {
                $this->fetchHmallProductDescriptions($hmallProduct, $brand, $updateTimestamps);

                // Update checkpoint only on success
                Cache::put($cacheKey, $hmallProduct->id, now()->addDays(7));

                $hasSucceeded = true;

                $this->detailCounter++;

                // Check if detail batch rest is needed
                if ($this->shouldDetailBatchRest()) {
                    $this->doDetailBatchRest();
                }
            } catch (Throwable $e) {
                // 403 propagated from inner method - stop the entire foreach
                if ($this->is403Error($e)) {
                    return false;
                }
                // Other errors: skip item (already logged in downloadHmallProductDescriptions / saveHmallProductDescriptions)
            }
        }

        // Clear checkpoint only if at least one item succeeded
        if ($hasSucceeded) {
            Cache::forget($cacheKey);
        }

        logger()->info("Completed fetching Hmall product descriptions for {$brand}");

        return true;
    }

    public function fetchHmallProductDescriptions(
        $hmallProduct,
        string $brand = 'UNIQLO',
        bool $updateTimestamps = false
    ): void {
        [$instruction, $sizeChart] = $this->downloadHmallProductDescriptions($hmallProduct, $brand);

        $this->saveHmallProductDescriptions($hmallProduct, $brand, $instruction, $sizeChart, $updateTimestamps);

        $this->randomDelay();
    }

    /**
     * 上架初期官網的說明常是空殼，存下來之後 instruction 不再是 null，每天的抓取不會再碰它，
     * 所以另外挑在售、說明實質空白的重抓，抓到有內容才寫回。被擋（403）回傳 false。
     */
    public function refetchEmptyHmallProductDescriptions(string $brand, int $limit = 100): bool
    {
        $presenter = app(HmallProductPresenter::class);
        $cacheKey = sprintf(self::CACHE_KEY_HMALL_DESCRIPTION_REFETCH, $brand);
        $startBelowId = Cache::get($cacheKey);

        $candidates = HmallProduct::where('brand', $brand)
            ->whereNull('stockout_at')
            ->whereNotNull('instruction')
            ->when($startBelowId, fn ($query) => $query->where('id', '<', $startBelowId))
            ->select(['id', 'product_code', 'instruction', 'size_chart'])
            ->lazyByIdDesc()
            ->filter(fn (HmallProduct $hmallProduct) => $presenter->getDescriptionBody($hmallProduct->instruction) === '')
            ->take($limit);

        $checked = 0;
        $filled = 0;

        foreach ($candidates as $hmallProduct) {
            try {
                [$instruction, $sizeChart] = $this->downloadHmallProductDescriptions($hmallProduct, $brand);
            } catch (Throwable $e) {
                if ($this->is403Error($e)) {
                    return false;
                }

                $instruction = null;
            }

            $checked++;
            Cache::put($cacheKey, $hmallProduct->id, now()->addDays(30));

            if ($instruction !== null && $presenter->getDescriptionBody($instruction) !== '') {
                $keptSizeChart = trim(strip_tags($sizeChart)) === '' ? (string) $hmallProduct->size_chart : $sizeChart;

                try {
                    $this->saveHmallProductDescriptions($hmallProduct, $brand, $instruction, $keptSizeChart);
                    $filled++;
                } catch (Throwable) {
                    // 已在 saveHmallProductDescriptions 記錄，換下一件
                }
            }

            $this->randomDelay();
        }

        if ($checked < $limit) {
            Cache::forget($cacheKey);
        }

        logger()->info('Refetched empty Hmall product descriptions', compact('brand', 'checked', 'filled'));

        return true;
    }

    private function saveHmallProductDescriptions(
        $hmallProduct,
        string $brand,
        string $instruction,
        string $sizeChart,
        bool $updateTimestamps = false
    ): void {
        try {
            $this->repository->updateProductDescriptionsFromV3($hmallProduct, $instruction, $sizeChart, $updateTimestamps);
        } catch (Throwable $e) {
            logger()->error('saveHmallProductDescriptions failed', [
                'brand' => $brand,
                'productCode' => $hmallProduct->product_code,
                'hmallProductId' => $hmallProduct->id,
                'error' => $e->getMessage(),
            ]);
            report($e);

            throw $e;
        }
    }

    /**
     * @return array{0: string, 1: string} 說明與尺寸表
     */
    private function downloadHmallProductDescriptions($hmallProduct, string $brand): array
    {
        $productCode = $hmallProduct->product_code;
        $download = fn (string $url) => retry(
            config('app.crawler.retry.times'),
            fn () => Http::withHeaders($this->buildHeaders())->throw()->get($url)->body(),
            fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
            fn ($e) => $this->shouldRetry($e),
        );

        try {
            return [
                $download($this->getV3DescriptionApiUrl($brand)."{$productCode}/zh_TW/instructionH5.html"),
                $download($this->getV3DescriptionApiUrl($brand)."{$productCode}/zh_TW/sizeAndTryOnH5.html"),
            ];
        } catch (Throwable $e) {
            if ($this->is403Error($e)) {
                logger()->error('downloadHmallProductDescriptions blocked (403)', [
                    'brand' => $brand,
                    'productCode' => $productCode,
                    'hmallProductId' => $hmallProduct->id,
                ]);
            } else {
                logger()->error('downloadHmallProductDescriptions error - max retry exceeded', [
                    'brand' => $brand,
                    'productCode' => $productCode,
                    'hmallProductId' => $hmallProduct->id,
                    'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
            report($e);

            throw $e;
        }
    }

    private function getV3SearchApiUrl($brand = 'UNIQLO'): string
    {
        if ($brand === 'GU') {
            return config('gu.api.v3.search.tw');
        }

        return config('uniqlo.api.v3.search.tw');
    }

    private function getV3DescriptionApiUrl($brand = 'UNIQLO'): string
    {
        if ($brand === 'GU') {
            return config('gu.api.v3.description.tw');
        }

        return config('uniqlo.api.v3.description.tw');
    }
}
