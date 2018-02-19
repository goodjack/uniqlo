<?php

namespace App\Presenters;

use App\Presenters\ProductPresenter;

class SearchPresenter
{
    public function __construct(ProductPresenter $productPresenter)
    {
        $this->productPresenter = $productPresenter;
    }

    public function getProductUrl($product)
    {
        return action('ProductController@show', ['id' => $product->id]);
    }
}
