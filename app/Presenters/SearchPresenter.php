<?php

namespace App\Presenters;

class SearchPresenter
{
    public function __construct(ProductPresenter $productPresenter)
    {
        $this->productPresenter = $productPresenter;
    }

    public function getProductUrl($product)
    {
        return $this->productPresenter->getProductUrl($product);
    }
}
