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

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => '商品期間限定特價中',
            'typeStyle' => 'negative',
            'typeIcon' => 'certificate',
        ]);
    }

    public function getSale()
    {
        $hmallProducts = $this->service->getSaleHmallProducts();
        $count = count($hmallProducts);

        $hmallProductList = $this->service->divideHmallProducts($hmallProducts);

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => '商品特價中',
            'typeStyle' => 'primary',
            'typeIcon' => 'shopping basket',
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

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => '新款商品',
            'typeStyle' => 'positive',
            'typeIcon' => 'leaf',
        ]);
    }
}
