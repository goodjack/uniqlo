<?php

namespace App\Repositories;

use App\Product;
use App\ProductHistory;
use Yish\Generators\Foundation\Repository\Repository;

class ProductHistoryRepository extends Repository
{
    protected $model;
    protected $product;

    public function __construct(Product $product, ProductHistory $productHistory)
    {
        $this->model = $productHistory;
        $this->product = $product;
    }

    /**
     * Get prices of products.
     *
     * @return array prices
     */
    public function getProductHistoryPrices()
    {
        // TODO: only select products that are still in stock
        $productIds = $this->product->select('id')->where('stockout', false)->get()->toArray();
        $productPrices = $this->model->whereIn('product_id', $productIds)->get(['product_id', 'price'])->groupBy('product_id');

        return $productPrices;
    }
}
