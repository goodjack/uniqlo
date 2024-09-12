<?php

namespace App\Services;

use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HmallProductService extends Service
{
    /** @var HmallProductRepository */
    protected $repository;

    protected $productRepository;

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

    public function fetchAllHmallProducts($brand = 'UNIQLO'): void
    {
        $searchApiUrl = $this->getV3SearchApiUrl($brand);

        $pageSize = 24;
        $page = 1;
        $productSum = 0;
        $retry = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                ])->retry(5, 1000)->post($searchApiUrl, [
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

                $retry = 0;

                sleep(1);
            } catch (Throwable $e) {
                if ($retry >= 5) {
                    Log::error('fetchAllHmallProducts error', [
                        'brand' => $brand,
                        'retry' => $retry,
                        'page' => $page,
                        'pageSize' => $pageSize,
                        'productSum' => $productSum,
                    ]);
                    report($e);

                    $retry = 0;

                    continue;
                }

                $retry++;
                $page--;

                sleep(1);
            }
        } while ($productSum >= $page++ * $pageSize);

        $this->repository->setStockoutHmallProducts($brand);
    }

    public function fetchAllHmallProductDescriptions(string $brand = 'UNIQLO', bool $updateTimestamps = false): void
    {
        $hmallProducts = HmallProduct::whereNull('instruction')
            ->where('brand', $brand)
            ->select(['id', 'product_code'])
            ->orderBy('id', 'desc')
            ->get();

        foreach ($hmallProducts as $hmallProduct) {
            $this->fetchHmallProductDescriptions($hmallProduct, $brand, $updateTimestamps);
        }
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

        do {
            try {
                $instructionResponse = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                ])->retry(5, 1000)->get($instructionApiUrl);

                $instruction = $instructionResponse->body();

                $sizeChartResponse = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                ])->retry(5, 1000)->get($sizeChartApiUrl);

                $sizeChart = $sizeChartResponse->body();

                $this->repository->updateProductDescriptionsFromV3(
                    $hmallProduct,
                    $instruction,
                    $sizeChart,
                    $updateTimestamps
                );

                $retry = 0;

                sleep(1);
            } catch (Throwable $e) {
                if ($retry >= 5) {
                    Log::error('fetchHmallProductDescriptions error', [
                        'brand' => $brand,
                        'retry' => $retry,
                        'productCode' => $productCode,
                        'hmallProductId' => $hmallProduct->id,
                    ]);
                    report($e);
                }

                $retry++;

                sleep(1);
            }
        } while ($retry > 0 && $retry <= 5);
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
