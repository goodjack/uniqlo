<?php

namespace App\Services;

use App\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yish\Generators\Foundation\Service\Service;

class HmallProductService extends Service
{
    /** @var HmallProductRepository */
    protected $repository;

    public function __construct(HmallProductRepository $repository)
    {
        $this->repository = $repository;
    }

    public function fetchAllHmallProducts()
    {
        $pageSize = 24;
        $page = 1;
        $productSum = 0;
        $retry = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                ])->post(config('uniqlo.api.hmall_search'), [
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
                $this->repository->saveProductsFromUniqloHmall($products);

                $productSum = $responseBody->resp[2]->productSum;

                $retry = 0;

                sleep(1);
            } catch (Throwable $e) {
                Log::error('fetchAllHmallProducts error', [
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
    }

    public function fetchAllHmallProductDescriptions($updateTimestamps = false)
    {
        $hmallProducts = HmallProduct::whereNull('instruction')
            ->select(['id', 'product_code'])
            ->orderBy('id', 'desc')
            ->get();

        foreach ($hmallProducts as $hmallProduct) {
            $productCode = $hmallProduct->product_code;
            $retry = 0;

            do {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => config('app.user_agent_mobile'),
                    ])->get(config('uniqlo.api.spu') . "{$productCode}.json");

                    $responseBody = json_decode($response->getBody());
                    $instruction = data_get($responseBody, 'desc.instruction');
                    $sizeChart = data_get($responseBody, 'desc.sizeChart');

                    $this->repository->updateProductDescriptionsFromUniqloSpu(
                        $hmallProduct,
                        $instruction,
                        $sizeChart,
                        $updateTimestamps
                    );

                    $retry = 0;

                    sleep(1);
                } catch (Throwable $e) {
                    Log::error('fetchAllHmallProductDescriptions error', [
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
}
