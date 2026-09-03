<?php

namespace App\Services;

use App\Enums\CrawlOutcome;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\Traits\AntiBlockingCrawler;
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
     * 缺貨判定（setStockoutHmallProducts）只在「這次從第 1 頁開始，而且每一頁
     * 都成功」時才跑。它的作法是把 updated_at 比今天早的商品標成下架，所以前提
     * 是這一輪真的把整份目錄看過一遍——少看任何一段，那一段的商品都會被誤判。
     *
     * 這個前提原本沒有被檢查，實際會這樣壞：某天中途幾頁失敗，後面成功的頁仍然
     * 推進 checkpoint，當天結束時 checkpoint 停在最後一頁之後；隔天排程不帶
     * --fresh，從那裡起跑、只抓到一頁空的、hasFailures 是 false，於是缺貨判定
     * 照跑，把整個品牌的商品全部標成下架，站上當天整片消失、後天才復原。
     */
    public function fetchAllHmallProducts($brand = 'UNIQLO', bool $fresh = false): CrawlOutcome
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
        $hasFailures = false;

        logger()->info("Fetching Hmall products for {$brand}, starting from page {$page}");

        do {
            try {
                $productSum = retry(
                    config('app.crawler.retry.times'),
                    function ($attempts) use ($searchApiUrl, $brand, $page, $pageSize) {
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

                        $this->repository->saveProductsFromV3($products, $brand);

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

                    return CrawlOutcome::Failed;
                }

                // retry() exhausted - skip page and continue
                $hasFailures = true;
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

        if ($hasSucceeded && ! $hasFailures) {
            Cache::forget($cacheKey);

            if ($startedFromFirstPage) {
                // 從第 1 頁掃到最後一頁、每頁都成功，這時候「沒看到」才等於下架
                $this->repository->setStockoutHmallProducts($brand);
                logger()->info("Completed fetching Hmall products for {$brand}");

                return CrawlOutcome::Succeeded;
            }

            // 從 checkpoint 續跑：這次只看了目錄的後半段，前半段的商品今天一次都
            // 沒被 updated_at 摸到，跑缺貨判定會把它們整批標成下架。checkpoint 清掉
            // 讓明天重新從第 1 頁完整掃，缺貨判定留給那一次。
            logger()->info('Resumed crawl finished cleanly - stockout deferred to the next full scan', [
                'brand' => $brand,
                'started_from_page' => $startPage,
            ]);

            return CrawlOutcome::PartiallySucceeded;
        }

        if ($hasSucceeded) {
            // 有幾頁失敗：保留 checkpoint 讓同一天可以接著跑，缺貨判定一樣不做
            logger()->error('Some pages failed - preserving checkpoint, skipping stockout', ['brand' => $brand]);

            return CrawlOutcome::PartiallySucceeded;
        }

        logger()->error('No pages were successfully fetched - preserving checkpoint', ['brand' => $brand]);

        return CrawlOutcome::Failed;
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
