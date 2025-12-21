<?php

namespace App\Services;

use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\Traits\AntiBlockingCrawler;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $retry = 0;
        $maxRetry = config('app.crawler.retry.laravel');

        Log::info("Fetching Hmall products for {$brand}, starting from page {$page}");

        do {
            try {
                $response = Http::withHeaders($this->buildHeaders())
                    ->retry($maxRetry, 1000)
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
                $products = $responseBody->resp[0]->productList;
                $this->repository->saveProductsFromV3($products, $brand);

                $productSum = $responseBody->resp[0]->productSum;

                // Update checkpoint
                Cache::set($cacheKey, $page + 1, now()->addDays(7));

                $retry = 0;

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 is a permanent block - stop immediately
                if ($this->is403Error($e)) {
                    Log::error('fetchAllHmallProducts blocked (403)', [
                        'brand' => $brand,
                        'page' => $page,
                        'pageSize' => $pageSize,
                    ]);
                    report($e);

                    return;
                }

                if ($retry >= $maxRetry) {
                    Log::error('fetchAllHmallProducts error - max retry exceeded', [
                        'brand' => $brand,
                        'retry' => $retry,
                        'page' => $page,
                        'pageSize' => $pageSize,
                        'productSum' => $productSum,
                        'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    report($e);

                    $retry = 0;

                    continue;
                }

                $retry++;
                $page--;

                $this->randomSleep();
            }
        } while ($productSum >= $page++ * $pageSize);

        // Clear checkpoint on successful completion
        Cache::forget($cacheKey);
        Log::info("Completed fetching Hmall products for {$brand}");

        $this->repository->setStockoutHmallProducts($brand);
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
            Log::info("Resuming Hmall product descriptions for {$brand} from ID {$lastProcessedId}");
        } else {
            Log::info("Fetching Hmall product descriptions for {$brand} from start");
        }

        $hmallProducts = $query->get();

        $this->resetDetailCounter();

        foreach ($hmallProducts as $hmallProduct) {
            $this->fetchHmallProductDescriptions($hmallProduct, $brand, $updateTimestamps);

            // Update checkpoint after each product
            Cache::set($cacheKey, $hmallProduct->id, now()->addDays(7));

            $this->detailCounter++;

            // Check if detail batch rest is needed
            if ($this->shouldDetailBatchRest()) {
                $this->doDetailBatchRest();
            }
        }

        // Clear checkpoint on completion
        Cache::forget($cacheKey);
        Log::info("Completed fetching Hmall product descriptions for {$brand}");
    }

    public function fetchHmallProductDescriptions(
        $hmallProduct,
        string $brand = 'UNIQLO',
        bool $updateTimestamps = false
    ): void {
        $productCode = $hmallProduct->product_code;
        $instructionApiUrl = $this->getV3DescriptionApiUrl($brand) . "{$productCode}/zh_TW/instructionH5.html";
        $sizeChartApiUrl = $this->getV3DescriptionApiUrl($brand) . "{$productCode}/zh_TW/sizeAndTryOnH5.html";
        $retry = 0;
        $maxRetry = config('app.crawler.retry.manual');

        do {
            try {
                $instructionResponse = Http::withHeaders($this->buildHeaders())
                    ->retry($maxRetry, 1000)
                    ->get($instructionApiUrl);

                $instruction = $instructionResponse->body();

                $sizeChartResponse = Http::withHeaders($this->buildHeaders())
                    ->retry($maxRetry, 1000)
                    ->get($sizeChartApiUrl);

                $sizeChart = $sizeChartResponse->body();

                $this->repository->updateProductDescriptionsFromV3(
                    $hmallProduct,
                    $instruction,
                    $sizeChart,
                    $updateTimestamps
                );

                $retry = 0;

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 is a permanent block - stop immediately
                if ($this->is403Error($e)) {
                    Log::error('fetchHmallProductDescriptions blocked (403)', [
                        'brand' => $brand,
                        'productCode' => $productCode,
                        'hmallProductId' => $hmallProduct->id,
                    ]);
                    report($e);

                    return;
                }

                if ($retry >= $maxRetry) {
                    Log::error('fetchHmallProductDescriptions error - max retry exceeded', [
                        'brand' => $brand,
                        'retry' => $retry,
                        'productCode' => $productCode,
                        'hmallProductId' => $hmallProduct->id,
                        'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    report($e);

                    return;
                }

                $retry++;

                $this->randomSleep();
            }
        } while ($retry > 0 && $retry <= $maxRetry);
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
