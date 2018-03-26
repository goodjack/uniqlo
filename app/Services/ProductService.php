<?php

namespace App\Services;

use App\Product;
use App\Repositories\ProductRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Yish\Generators\Foundation\Service\Service;

use function Functional\each;
use function Functional\flat_map;
use function Functional\map;

class ProductService extends Service
{
    protected $repository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getStyleDictionaries(Product $product)
    {
        return $this->productRepository->getStyleDictionaries($product);
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
                        'order' => 'asc',
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

    public function fetchAllStyleDictionaries()
    {
        $client = new Client();

        $response = $client->request(
            'GET',
            env('UQ_API_STYLE_DICTIONARY_LIST'),
            [
                'query' => [
                    'date' => Carbon::today(),
                    'at' => 'include_uq_plugin',
                    't' => 3,
                    'id' => 0
                ]
            ]
        );

        $styleDictionaries = json_decode($response->getBody());
        $imgDir = $styleDictionaries->imgdir;

        each($styleDictionaries->imgs, function ($styleDictionary) use ($imgDir) {
            $client = new Client();

            $response = $client->request(
                'GET',
                env('UQ_API_STYLE_DICTIONARY_DETAIL'),
                [
                    'query' => [
                        'date' => Carbon::today()->toDateString(),
                        'at' => 'include_uq_plugin',
                        't' => 'd',
                        'id' => $styleDictionary->id
                    ]
                ]
            );

            $detail = json_decode($response->getBody());
            $this->productRepository->saveStyleDictionaryFromUniqlo($detail, $imgDir);

            sleep(random_int(1, 3));
        });
    }

    /**
     * Set stockout status to the products.
     *
     * @return void
     */
    public function setStockoutProducts()
    {
        $this->productRepository->setStockoutProducts();
    }
}
