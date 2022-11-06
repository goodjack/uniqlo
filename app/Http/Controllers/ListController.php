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
            'description' => '排序依據：特價幅度 > 評論數 > 評分 > 上架時間',
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
            'description' => '排序依據：特價幅度 > 評論數 > 評分 > 上架時間',
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
            'description' => '排序依據：評論數 > 評分 > 上架時間',
        ]);
    }

    public function getTopWearing()
    {
        $hmallProducts = $this->service->getTopWearingHmallProducts();
        $count = count($hmallProducts);

        $hmallProductList = $this->service->divideHmallProducts($hmallProducts);

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => '熱門穿搭商品',
            'typeStyle' => 'top-wearing',
            'typeIcon' => 'camera retro',
            'description' => '排序依據：網友穿搭數 > 評論數 > 評分 > 上架時間',
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
            'description' => '排序依據：特價幅度 > 評論數 > 評分 > 上架時間',
        ]);
    }

    public function getComingSoon()
    {
        $hmallProducts = $this->service->getComingSoonHmallProducts();
        $count = count($hmallProducts);

        $hmallProductList = $this->service->divideHmallProducts($hmallProducts);

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => '即將上市商品',
            'typeStyle' => 'coming-soon',
            'typeIcon' => 'checked calendar',
            'description' => '排序依據：特價幅度 > 評論數 > 評分 > 上架時間',
        ]);
    }

    public function getMultiBuy()
    {
        $hmallProducts = $this->service->getMultiBuyHmallProducts();
        $count = count($hmallProducts);

        $hmallProductList = $this->service->divideHmallProducts($hmallProducts);

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => '合購商品',
            'typeStyle' => 'info',
            'typeIcon' => 'cubes',
            'description' => '排序依據：評論數 > 評分 > 上架時間',
        ]);
    }

    public function getOnlineSpecial()
    {
        $hmallProducts = $this->service->getOnlineSpecialHmallProducts();
        $count = count($hmallProducts);

        $hmallProductList = $this->service->divideHmallProducts($hmallProducts);

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => '網路獨家販售商品',
            'typeStyle' => 'online-special',
            'typeIcon' => 'tv',
            'description' => '排序依據：特價幅度 > 評論數 > 評分 > 上架時間',
        ]);
    }
}
