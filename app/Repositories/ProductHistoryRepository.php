<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductHistory;
use Illuminate\Support\Facades\DB;

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
        $productIds = $this->product->select('id')
            ->where('stockout', false)
            ->where('limit_sales_end_msg', '')
            ->get()->toArray();

        $productPrices = $this->model->whereIn('product_id', $productIds)->get(['product_id', 'price'])->groupBy('product_id');

        return $productPrices;
    }

    /**
     * Get the min price and the max price from the products table.
     *
     * @return array prices
     */
    public function getMinPricesAndMaxPrices(bool $today = false)
    {
        $prices = $this->model
            ->select('product_id', DB::raw('MIN(price) as min_price'), DB::raw('MAX(price) as max_price'))
            ->groupBy('product_id');

        if ($today) {
            $prices->havingRaw('MAX(created_at) > ?', [today()]);
        }

        return $prices->get();
    }
}
