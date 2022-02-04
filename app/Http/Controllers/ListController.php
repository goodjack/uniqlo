<?php

namespace App\Http\Controllers;

use App\Services\ListService;

class ListController extends Controller
{
    protected $service;

    public function __construct(ListService $service)
    {
        $this->service = $service;
    }

    public function getLimitedOffers()
    {
        $hmallProducts = $this->service->getLimitedOfferHmallProducts();
        $count = count($hmallProducts);

        $hmallProductList = $this->service->divideHmallProducts($hmallProducts);

        return view('lists.limited-offers', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
        ]);
    }

    public function getSale()
    {
        $hmallProducts = $this->service->getSaleHmallProducts();
        $count = count($hmallProducts);

        $hmallProductList = $this->service->divideHmallProducts($hmallProducts);

        return view('lists.sale', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
        ]);
    }

    public function getMostReviewed()
    {
        $hmallProducts = $this->service->getMostReviewedHmallProducts();
        $count = count($hmallProducts);

        $hmallProductList = $this->service->divideHmallProducts($hmallProducts);

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => '熱門評論商品',
            'typeStyle' => 'most-reviewed',
            'typeIcon' => 'comments outline',
        ]);
    }

    public function getNew()
    {
        $hmallProducts = $this->service->getNewHmallProducts();
        $count = count($hmallProducts);

        $hmallProductList = $this->service->divideHmallProducts($hmallProducts);

        return view('lists.new', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
        ]);
    }
}
