<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Yish\Generators\Foundation\Service\Service;

use function Functional\flat_map;
use function Functional\map;

class ProductService extends Service
{
    protected $repository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getStyleDictionary($productID)
    {
        $client = new Client();

        $response = $client->request(
            'GET',
            env('UQ_API_STYLE_DICTIONARY'),
            [
                'query' => ['pub' => $productID]
            ]
        );

        return json_decode($response->getBody());
    }

    public function getStock($productID)
    {
        $client = new Client();

        $response = $client->request(
            'GET',
            env('UQ_API_STATUS'),
            [
                'query' => [
                    'client_id' => 'uqsp-tw',
                    'sku_code' => $productID,
                    'store_id' => '10400044,10400002'
                ]
            ]
        );

        $apiResult = json_decode($response->getBody());

        $stock = flat_map($apiResult->statuses, function ($store) {
            $storeID = key($store);
            $items = $store->$storeID->items;

            return map($items, function ($item) use ($storeID) {
                return [
                    'store_id' => $storeID,
                    'sku_code' => $item->sku_code,
                    'stock_status' => $item->stock_status
                ];
            });
        });

        return $stock;
    }

    public function fetchAllProducts()
    {
        $client = new Client();
        $limit = 20;
        $page = 1;
        $total = 0;

        do {
            $response = $client->request(
                'GET',
                env('UQ_API_PRODUCTS'),
                [
                    'headers' => [
                        'User-Agent' => env('USER_AGENT_MOBILE')
                    ],
                    'query' => [
                        'order' => $asc,
                        'limit' => $limit,
                        'page' => $page
                    ]
                ]
            );
            
            $products = json_decode($response->getBody());
            $this->productRepository->saveProductsFromUniqlo($products->records);

            $total = $products->total;
            sleep(random_int(1, 5));
        } while ($total >= $page++ * $limit);
    }
}
