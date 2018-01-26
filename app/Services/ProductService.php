<?php

namespace App\Services;

use Cache;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Yish\Generators\Foundation\Service\Service;

use function Functional\flat_map;
use function Functional\map;

class ProductService extends Service
{
    protected $repository;

    public function getProductInfo($productID)
    {
        $client = new Client();

        $response = $client->request(
            'GET',
            "http://www.uniqlo.com/tw/spa-catalog/products/{$productID}",
            [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) CriOS/56.0.2924.75 Mobile/14E5239e Safari/602.1'
                ]
            ]
        );

        return json_decode($response->getBody());
    }

    public function getStyleDictionary($productID)
    {
        $client = new Client();

        $response = $client->request(
            'GET',
            'http://www.uniqlo.com/tw/styledictionary/api/sdapi2.php',
            [
                'query' => ['t' => '3', 'pub' => $productID]
            ]
        );

        return json_decode($response->getBody());
    }

    public function getProductStock($productID)
    {
        $statusApi = env('UQ_API_STATUS');

        $client = new Client();

        $response = $client->request(
            'GET',
            $statusApi,
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

    public function getStoreList()
    {
        $minutes = 1440; // 1 day

        $storeList = Cache::remember('store_list', $minutes, function () {
            $client = new Client();
            $limit = 10;
            $times = 1;
            $totalCount = 0;
            $storeList = [];

            do {
                $response = json_decode($client->request(
                    'GET',
                    env('UQ_API_STORE_LIST'),
                    [
                        'query' => ['limit' => $limit, 'r' => 'storelocator']
                    ]
                )->getBody());

                $totalCount = $response->result->total_count;
                array_marge($storeList, $response->result->stores);
            } while ($times*$limit <= $totalCount);
        });
        

        return $storeList;
    }
}
