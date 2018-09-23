<?php

namespace App\Services;

use App\Foundations\DivideProducts;
use App\Product;
use App\Repositories\ProductRepository;
use App\Repositories\ProductHistoryRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Yish\Generators\Foundation\Service\Service;

use function Functional\each;
use function Functional\flat_map;
use function Functional\map;

class ProductService extends Service
{
    use divideProducts;

    protected $repository;
    protected $productRepository;
    protected $productHistoryRepository;

    public function __construct(ProductRepository $productRepository, ProductHistoryRepository $productHistoryRepository)
    {
        $this->productRepository = $productRepository;
        $this->productHistoryRepository = $productHistoryRepository;
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

    public function fetchMultiBuyProducts()
    {
        $ids = $this->productRepository->getMultiBuyProductIds();
        $ids->each(function ($id) {
            $client = new Client();

            $response = $client->request(
                'GET',
                env('UQ_API_PRODUCTS_FOR_MULTI_BUY'),
                [
                    'query' => [
                        'format' => 'json',
                        'product_cd' => $id,
                    ]
                ]
            );

            $productInfo = json_decode($response->getBody());
            $promo = data_get($productInfo, 'l2_goods_list.0.promo_rule_info');
            if (!empty($promo)) {
                $this->productRepository->saveMultiBuyPromo($id, $promo);
            }

            sleep(1);
        });
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
    public function setStockoutProductTags()
    {
        $this->productRepository->setStockoutProductTags();
    }

    /**
     * Get all of the stockout products.
     *
     * @return array|null Array of Product models
     */
    public function getStockoutProducts()
    {
        $products = $this->productRepository->getStockoutProducts();

        return $this->divideProducts($products);
    }

    /**
     * Get sale products.
     *
     * @return array|null Sale products
     */
    public function getSaleProducts()
    {
        // $productHistoryPrices = $this->productHistoryRepository->getProductHistoryPricesForHiddenSale();

        // $saleProductIds = $productHistoryPrices
        //     ->reduce(function ($carry, $productPrices) {
        //         $productId = $productPrices->first()->product_id;

        //         $medianPrice = collect($productPrices->map(function ($product) {
        //             return $product->price;
        //         }))->median();

        //         $lastPrice = $productPrices->last()->price;

        //         if ($medianPrice > $lastPrice) {
        //             $carry[] = $productId;
        //         }

        //         return $carry;
        //     }, []);

        // $products = $this->productRepository->getProductsByIds($saleProductIds);

        $products = $this->productRepository->getSaleProducts();

        return $this->divideProducts($products);
    }

    /**
     * Get limited offer products.
     *
     * @return array|null Limited offer products
     */
    public function getLimitedOfferProducts()
    {
        $products = $this->productRepository->getLimitedOfferProducts();

        return $this->divideProducts($products);
    }

    /**
     * Get MULTI_BUY products.
     *
     * @return array|null MULTI_BUY products
     */
    public function getMultiBuyProducts()
    {
        $products = $this->productRepository->getMultiBuyProducts();

        return $this->divideProducts($products);
    }

    /**
     * Get new products.
     *
     * @return array|null new products
     */
    public function getNewProducts()
    {
        $products = $this->productRepository->getNewProducts();

        return $this->divideProducts($products);
    }

    /**
     * Set the min price and the max price to the products.
     *
     * @param boolean $today
     * @return void
     */
    public function setMinPricesAndMaxPrices(bool $today = false)
    {
        $prices = $this->productHistoryRepository->getMinPricesAndMaxPrices($today);
        $this->productRepository->setMinPricesAndMaxPrices($prices);
    }

    public function getRelatedProducts($product)
    {
        return $this->productRepository->getRelatedProducts($product);
    }
}
