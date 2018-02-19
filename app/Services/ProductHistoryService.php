<?php

namespace App\Services;

use Yish\Generators\Foundation\Service\Service;

class ProductHistoryService extends Service
{
    protected $repository;

    public function getHighestPrice($productHistories)
    {
        return $productHistories->max('price');
    }

    public function getLowestPrice($productHistories)
    {
        return $productHistories->min('price');
    }
}
