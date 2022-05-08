<?php

namespace App\Services;

use App\HmallProduct;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yish\Generators\Foundation\Service\Service;

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

    public function getStyles(HmallProduct $hmallProduct)
    {
        return $this->repository->getStyles($hmallProduct);
    }

    public function fetchAllHmallProducts($brand = 'UNIQLO')
    {
        $hmallSearchApiUrl = $this->getHmallSearchApiUrl($brand);

        $pageSize = 24;
        $page = 1;
        $productSum = 0;
        $retry = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                ])->post($hmallSearchApiUrl, [
                    'url' => config('uniqlo.data.hmall_search.url') . $page,
                    'pageInfo' => ['page' => $page, 'pageSize' => $pageSize, 'withSideBar' => 'Y'],
                    'belongTo' => 'pc',
                    'rank' => 'overall',
                    'priceRange' => ['low' => 0, 'high' => 0],
                    'color' => [],
                    'size' => [],
                    'season' => [],
                    'material' => [],
                    'sex' => [],
                    'identity' => [],
                    'insiteDescription' => '',
                    'exist' => [],
                    'searchFlag' => true,
                    'description' => config('uniqlo.data.hmall_search.description'),
                ]);

                $responseBody = json_decode($response->getBody());
                $products = $responseBody->resp[1];
                $this->repository->saveProductsFromHmall($products, $brand);

                $productSum = $responseBody->resp[2]->productSum;

                $retry = 0;

                sleep(1);
            } catch (Throwable $e) {
                Log::error('fetchAllHmallProducts error', [
                    'brand' => $brand,
                    'retry' => $retry,
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'productSum' => $productSum,
                ]);
                report($e);

                if ($retry >= 3) {
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

    public function getHmallSearchApiUrl($brand = 'UNIQLO')
    {
        if ($brand === 'GU') {
            return config('gu.api.hmall_search');
        }

        return config('uniqlo.api.hmall_search');
    }

    public function fetchAllHmallProductDescriptions($brand = 'UNIQLO', $updateTimestamps = false)
    {
        $hmallProducts = HmallProduct::whereNull('instruction')
            ->where('brand', $brand)
            ->select(['id', 'product_code'])
            ->orderBy('id', 'desc')
            ->get();

        $spuApiUrl = $this->getSpuApiUrl($brand);

        foreach ($hmallProducts as $hmallProduct) {
            $productCode = $hmallProduct->product_code;
            $retry = 0;

            do {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => config('app.user_agent_mobile'),
                    ])->get("{$spuApiUrl}{$productCode}.json");

                    $responseBody = json_decode($response->getBody());
                    $instruction = data_get($responseBody, 'desc.instruction');
                    $sizeChart = data_get($responseBody, 'desc.sizeChart');

                    $this->repository->updateProductDescriptionsFromSpu(
                        $hmallProduct,
                        $instruction,
                        $sizeChart,
                        $updateTimestamps
                    );

                    $retry = 0;

                    sleep(1);
                } catch (Throwable $e) {
                    Log::error('fetchAllHmallProductDescriptions error', [
                        'brand' => $brand,
                        'retry' => $retry,
                        'productCode' => $productCode,
                    ]);
                    report($e);

                    $retry++;

                    sleep(1);
                }
            } while ($retry > 0 && $retry <= 3);
        }
    }

    public function getSpuApiUrl($brand = 'UNIQLO')
    {
        if ($brand === 'GU') {
            return config('gu.api.spu');
        }

        return config('uniqlo.api.spu');
    }
}
