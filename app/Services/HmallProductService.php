<?php

namespace App\Services;

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

    public function fetchAllHmallProducts($brand = 'UNIQLO', bool $fresh = false): void
    {
        $searchApiUrl = $this->getV3SearchApiUrl($brand);

        $pageSize = 24;
        $cacheKey = sprintf(self::CACHE_KEY_HMALL_PRODUCTS_PAGE, $brand);
        $page = $fresh ? 1 : (Cache::get($cacheKey) ?? 1);
        $productSum = 0;
        $hasSucceeded = false;

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
                    // TODO: Consider running stockout processing on partial data, or at least notifying
                    logger()->warning('403 blocked - skipping stockout processing', ['brand' => $brand]);
                    logger()->error('fetchAllHmallProducts blocked (403)', [
                        'brand' => $brand,
                        'page' => $page,
                        'pageSize' => $pageSize,
                    ]);
                    report($e);

                    return;
                }

                // retry() exhausted - skip page and continue
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

        if ($hasSucceeded) {
            // Clear checkpoint on successful completion
            Cache::forget($cacheKey);
            logger()->info("Completed fetching Hmall products for {$brand}");

            $this->repository->setStockoutHmallProducts($brand);
        } else {
            logger()->warning('No pages were successfully fetched - preserving checkpoint', ['brand' => $brand]);
        }
    }

    public function fetchAllHmallProductDescriptions(string $brand = 'UNIQLO', bool $updateTimestamps = false, bool $fresh = false): void
    {
        $cacheKey = sprintf(self::CACHE_KEY_HMALL_DESCRIPTIONS, $brand);
        $lastProcessedId = $fresh ? null : Cache::get($cacheKey);

        $query = HmallProduct::whereNull('instruction')
            ->where('brand', $brand)
            ->select(['id', 'product_code'])
            ->orderBy('id', 'desc');

        if ($lastProcessedId) {
            $query->where('id', '<', $lastProcessedId);
            logger()->info("Resuming Hmall product descriptions for {$brand} from ID {$lastProcessedId}");
        } else {
            logger()->info("Fetching Hmall product descriptions for {$brand} from start");
        }

        $hmallProducts = $query->get();

        $this->resetDetailCounter();

        foreach ($hmallProducts as $hmallProduct) {
            try {
                $this->fetchHmallProductDescriptions($hmallProduct, $brand, $updateTimestamps);

                // Update checkpoint only on success
                Cache::put($cacheKey, $hmallProduct->id, now()->addDays(7));

                $this->detailCounter++;

                // Check if detail batch rest is needed
                if ($this->shouldDetailBatchRest()) {
                    $this->doDetailBatchRest();
                }
            } catch (Throwable $e) {
                // 403 propagated from inner method - stop the entire foreach
                if ($this->is403Error($e)) {
                    return;
                }
                // Other errors: skip item (already logged in fetchHmallProductDescriptions)
            }
        }

        // Clear checkpoint on completion
        Cache::forget($cacheKey);
        logger()->info("Completed fetching Hmall product descriptions for {$brand}");
    }

    public function fetchHmallProductDescriptions(
        $hmallProduct,
        string $brand = 'UNIQLO',
        bool $updateTimestamps = false
    ): void {
        $productCode = $hmallProduct->product_code;
        $instructionApiUrl = $this->getV3DescriptionApiUrl($brand) . "{$productCode}/zh_TW/instructionH5.html";
        $sizeChartApiUrl = $this->getV3DescriptionApiUrl($brand) . "{$productCode}/zh_TW/sizeAndTryOnH5.html";

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
