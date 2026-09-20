<?php

namespace App\Services;

use App\Enums\CrawlOutcome;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\Traits\AntiBlockingCrawler;
use App\Support\CrawlResult;
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
     * 抓一個品牌的完整商品目錄。
     *
     * 缺貨判定（setStockoutHmallProducts）的作法是把 updated_at 比今天早的商品
     * 標成下架，前提是這一輪真的把整份目錄看過一遍——少看任何一段，那一段的商品
     * 都會被誤判。所以它只在「這次從第 1 頁開始，而且每一頁都完整抓到」時才跑。
     *
     * 這個前提原本沒有被檢查，實際會這樣壞：某天中途幾頁失敗，後面成功的頁仍然
     * 推進 checkpoint，當天結束時 checkpoint 停在最後一頁之後；隔天排程不帶
     * --fresh，從那裡起跑、只抓到一頁空的、沒有任何失敗，於是缺貨判定照跑，把整個
     * 品牌的商品全部標成下架，站上當天整片消失、後天才復原。
     *
     * 失敗分兩種，處理方式完全不同，不能混成同一個旗標：
     *
     * 1. 整頁沒抓到（HTTP 失敗、回傳格式不對）＝目錄不完整，缺貨判定一定不能做。
     * 2. 頁面完整抓到、只有個別商品寫不進資料庫＝整份目錄其實都看過了，只是那幾件
     *    的 updated_at 沒被摸到。這時候照跑缺貨判定會把那幾件冤枉標成下架，所以
     *    改成「跑，但把那幾件排除掉」。
     *
     * 混在一起的後果是缺貨判定永遠不會執行：只要有一件商品固定寫不進去，第一天
     * 完整掃完會被當成部分失敗而保留 checkpoint，第二天從 checkpoint 續跑抓到空頁
     * 又因為不是完整掃描而跳過，第三天回到第一天——已經下架的商品就一直掛在站上。
     *
     * 回傳值除了結果本身，還帶一句說明：部分成功有好幾種，通知只寫「部分成功」
     * 看不出這一輪到底有沒有做缺貨判定。
     */
    public function fetchAllHmallProducts($brand = 'UNIQLO', bool $fresh = false): CrawlResult
    {
        $searchApiUrl = $this->getV3SearchApiUrl($brand);

        $pageSize = (int) config('app.crawler.page_sizes.hmall_products');
        if ($pageSize < 1) {
            throw new Exception('CRAWLER_HMALL_PRODUCTS_PAGE_SIZE is not configured.');
        }

        $cacheKey = sprintf(self::CACHE_KEY_HMALL_PRODUCTS_PAGE, $brand);

        // Clear checkpoint if fresh
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $startPage = (int) ($fresh ? 1 : (Cache::get($cacheKey) ?? 1));
        // 缺貨判定的前提是「這次真的把整份目錄看過一遍」，所以要記住起點
        $startedFromFirstPage = $startPage === 1;

        $page = $startPage;
        $productSum = 0;
        $hasSucceeded = false;
        // 整頁沒抓到：目錄有缺口
        $hasPageFailures = false;
        // 頁面抓到了、個別商品寫不進去，而且知道是哪幾件：缺貨判定要排除它們
        $failedProductCodes = [];
        // 同樣是個別商品寫不進去，但連商品編號都拿不到，沒辦法排除
        $unidentifiedFailures = 0;

        logger()->info("Fetching Hmall products for {$brand}, starting from page {$page}");

        do {
            try {
                $productSum = retry(
                    config('app.crawler.retry.times'),
                    function ($attempts) use (
                        $searchApiUrl,
                        $brand,
                        $page,
                        $pageSize,
                        &$failedProductCodes,
                        &$unidentifiedFailures
                    ) {
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

                        // 逐商品的寫入例外在 repository 裡被吞掉了（一筆壞資料不該
                        // 讓同一頁其他幾十件寫不進去）。這一頁的目錄內容其實看完了，
                        // 所以不算整頁失敗，但寫不進去的那幾件要記下來，最後從缺貨
                        // 判定裡排除，不然它們會被當成「今天沒看到」而標成下架。
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

                        return $responseBody->resp[0]->productSum;
                    },
                    fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                    fn ($e) => $this->shouldRetry($e),
                );

                $hasSucceeded = true;

                // Update checkpoint
                Cache::put($cacheKey, $page + 1, now()->addDays(7));

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 is a permanent block - stop immediately
                if ($this->is403Error($e)) {
                    logger()->error('fetchAllHmallProducts blocked (403)', [
                        'brand' => $brand,
                        'page' => $page,
                        'pageSize' => $pageSize,
                    ]);
                    report($e);

                    return new CrawlResult(CrawlOutcome::Failed);
                }

                // retry() exhausted - skip page and continue
                $hasPageFailures = true;
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
        } while ($productSum >= ($page - 1) * $pageSize);

        if (! $hasSucceeded) {
            logger()->error('No pages were successfully fetched - preserving checkpoint', ['brand' => $brand]);

            return new CrawlResult(CrawlOutcome::Failed);
        }

        if ($hasPageFailures) {
            // 有幾頁整頁沒抓到：這一輪看到的目錄是殘缺的，缺貨判定一定不能做。
            // checkpoint 保留，讓同一天可以接著跑完剩下的頁。
            logger()->error('Some pages failed - preserving checkpoint, skipping stockout', ['brand' => $brand]);

            return new CrawlResult(CrawlOutcome::PartiallySucceeded, '未執行缺貨判定，目錄有缺頁');
        }

        // 每一頁都完整抓到了，checkpoint 沒有續跑的價值，一律清掉讓下一輪從第 1 頁開始。
        // 個別商品寫入失敗不保留 checkpoint：保留只會讓隔天從空頁起跑、白白跳過一次
        // 完整掃描，而完整掃描才是缺貨判定唯一的機會。
        Cache::forget($cacheKey);

        // 從 checkpoint 續跑：這次只看了目錄的後半段，前半段的商品這一輪一次都沒被
        // updated_at 摸到，跑缺貨判定會把它們整批標成下架。缺貨判定留給下一次完整掃描。
        if (! $startedFromFirstPage) {
            logger()->info('Resumed crawl finished cleanly - stockout deferred to the next full scan', [
                'brand' => $brand,
                'started_from_page' => $startPage,
            ]);

            return new CrawlResult(
                CrawlOutcome::PartiallySucceeded,
                '未執行缺貨判定，這一輪是從上次中斷的地方接著跑'
            );
        }

        $failedProductCodes = array_values(array_unique($failedProductCodes));

        // 有商品寫不進去、而且拿不到它的商品編號：沒辦法把它排除在缺貨判定之外，
        // 只能整輪不做。checkpoint 已經清掉，下一輪從第 1 頁重來。
        if ($unidentifiedFailures > 0) {
            logger()->error('Product failures without a product code - skipping stockout', [
                'brand' => $brand,
                'unidentified_failures' => $unidentifiedFailures,
                'failed_product_codes' => $failedProductCodes,
            ]);

            return new CrawlResult(
                CrawlOutcome::PartiallySucceeded,
                "未執行缺貨判定，{$unidentifiedFailures} 件失敗資料缺少商品編號"
            );
        }

        // 從第 1 頁掃到最後一頁、每一頁都完整抓到，這時候「沒看到」才等於下架。
        // 寫入失敗的那幾件今天在來源其實還在，排除掉不讓它們被冤枉標成下架。
        $this->repository->setStockoutHmallProducts($brand, null, $failedProductCodes);

        if ($failedProductCodes === []) {
            logger()->info("Completed fetching Hmall products for {$brand}");

            return new CrawlResult(CrawlOutcome::Succeeded);
        }

        logger()->error('Stockout ran with the products that failed to save excluded', [
            'brand' => $brand,
            'excluded_product_codes' => $failedProductCodes,
        ]);

        return new CrawlResult(
            CrawlOutcome::PartiallySucceeded,
            sprintf('已執行缺貨判定，排除 %d 件寫入失敗商品', count($failedProductCodes))
        );
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
                // Other errors: skip item (already logged in fetchHmallProductDescriptions)
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
        $productCode = $hmallProduct->product_code;
        $instructionApiUrl = $this->getV3DescriptionApiUrl($brand)."{$productCode}/zh_TW/instructionH5.html";
        $sizeChartApiUrl = $this->getV3DescriptionApiUrl($brand)."{$productCode}/zh_TW/sizeAndTryOnH5.html";

        try {
            $instruction = retry(
                config('app.crawler.retry.times'),
                function ($attempts) use ($instructionApiUrl) {
                    $response = Http::withHeaders($this->buildHeaders())
                        ->throw()
                        ->get($instructionApiUrl);

                    return $response->body();
                },
                fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                fn ($e) => $this->shouldRetry($e),
            );

            $sizeChart = retry(
                config('app.crawler.retry.times'),
                function ($attempts) use ($sizeChartApiUrl) {
                    $response = Http::withHeaders($this->buildHeaders())
                        ->throw()
                        ->get($sizeChartApiUrl);

                    return $response->body();
                },
                fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                fn ($e) => $this->shouldRetry($e),
            );

            $this->repository->updateProductDescriptionsFromV3(
                $hmallProduct,
                $instruction,
                $sizeChart,
                $updateTimestamps
            );

            $this->randomDelay();
        } catch (Throwable $e) {
            // 403 is a permanent block - propagate to caller
            if ($this->is403Error($e)) {
                logger()->error('fetchHmallProductDescriptions blocked (403)', [
                    'brand' => $brand,
                    'productCode' => $productCode,
                    'hmallProductId' => $hmallProduct->id,
                ]);
                report($e);

                throw $e;
            }

            // retry() exhausted - log and propagate so caller can skip and not count this item
            logger()->error('fetchHmallProductDescriptions error - max retry exceeded', [
                'brand' => $brand,
                'productCode' => $productCode,
                'hmallProductId' => $hmallProduct->id,
                'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                'error' => $e->getMessage(),
            ]);
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
