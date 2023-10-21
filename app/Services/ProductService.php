<?php

namespace App\Services;

use App\Foundations\DivideProducts;
use App\Models\Product;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StyleRepository;
use GuzzleHttp\Client;

class ProductService extends Service
{
    use divideProducts;

    protected $repository;

    protected $hmallProductRepository;

    protected $productRepository;

    protected $styleRepository;

    public function __construct(
        HmallProductRepository $hmallProductRepository,
        ProductRepository $productRepository,
        StyleRepository $styleRepository
    ) {
        $this->hmallProductRepository = $hmallProductRepository;
        $this->productRepository = $productRepository;
        $this->styleRepository = $styleRepository;
    }

    public function getStyleDictionaries(Product $product)
    {
        return $this->productRepository->getStyleDictionaries($product);
    }

    public function getStyles(Product $product)
    {
        return $this->productRepository->getStyles($product);
    }

    public function getSuggestProducts($selfProductId, $styles, $havingProducts = 3)
    {
        $productIds = [];

        while (count($productIds) <= 0 && $havingProducts >= 0) {
            $productIds = $this->styleRepository->getSuggestProductIds($styles, $havingProducts--);

            $productIds = $productIds->reject(function ($productId) use ($selfProductId) {
                return $productId === $selfProductId;
            });
        }

        return $this->productRepository->getProductsByIds($productIds);
    }

    public function fetchAllStyles()
    {
        $dptIds = collect([
            'men',
            'women',
            'kids',
            'baby',
            'UserMen',
            'UserWomen',
            'UserKids',
            'UserBaby',
            'ModelMen',
            'ModelWomen',
        ]);

        $dptIds->each(function ($dptId) {
            return $this->fetchStylesByDptId($dptId);
        });
    }

    private function fetchStylesByDptId($dptId)
    {
        $client = new Client();
        $limit = 50;
        $offset = 0;
        $styleCount = 0;

        do {
            $response = $client->request(
                'GET',
                config('uniqlo.api.style_book_list'),
                [
                    'headers' => [
                        'User-Agent' => config('app.user_agent_mobile'),
                    ],
                    'query' => [
                        'dptId' => $dptId,
                        'lang' => 'zh',
                        'limit' => $limit,
                        'locale' => 'tw',
                        'offset' => $offset,
                    ],
                ]
            );

            $responseBody = json_decode($response->getBody());

            $styles = $responseBody->result->styles;
            $this->fetchStyleDetails($styles);

            $styleCount = $responseBody->result->styleCount;

            $offset += $limit;
        } while ($styleCount >= $offset);
    }

    private function fetchStyleDetails($styles)
    {
        $styles = collect($styles);
        $styles->each(function ($style) {
            $client = new Client();

            $response = $client->request(
                'GET',
                config('uniqlo.api.style_book_detail'),
                [
                    'headers' => [
                        'User-Agent' => config('app.user_agent_mobile'),
                    ],
                    'query' => [
                        'lang' => 'zh',
                        'limit' => 4,
                        'locale' => 'tw',
                        'styleId' => $style->styleId,
                    ],
                ]
            );

            $result = json_decode($response->getBody())->result;
            $this->productRepository->saveStyleFromUniqloStyleBook($result);

            sleep(1);
        });
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
     * Get most reviewed products.
     *
     * @return array|null most review products
     */
    public function getMostReviewedProducts()
    {
        $products = $this->productRepository->getMostReviewedProducts();

        return $this->divideProducts($products);
    }

    public function getRelatedProducts($product)
    {
        return $this->productRepository->getRelatedProducts($product);
    }

    public function getRelatedHmallProducts($product)
    {
        return $this->hmallProductRepository->getRelatedHmallProductsForProduct($product);
    }
}
