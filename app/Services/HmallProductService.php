<?php

namespace App\Services;

use App\Repositories\HmallProductRepository;
use Illuminate\Support\Facades\Http;
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

                sleep(1);
            } catch (Throwable $e) {
                report($e);
            }
        } while ($productSum >= $page++ * $pageSize);
    }
}
