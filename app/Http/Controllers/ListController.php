<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Services\ListService;

class ListController extends Controller
{
    protected $service;

    public function __construct(ListService $service)
    {
        $this->service = $service;
    }

    public function getLimitedOffers(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getLimitedOfferHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '商品期間限定特價中',
            'negative',
            'certificate',
            '排序依據：特價幅度 > 評論數 > 評分 > 上架時間'
        );
    }

    public function getSale(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getSaleHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '商品特價中',
            'primary',
            'shopping basket',
            '排序依據：特價幅度 > 評論數 > 評分 > 上架時間'
        );
    }

    public function getMostReviewed(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getMostReviewedHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '熱門評論商品',
            'most-reviewed',
            'comments outline',
            '排序依據：評論數 > 評分 > 上架時間'
        );
    }

    public function getTopWearing(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getTopWearingHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '熱門穿搭商品',
            'top-wearing',
            'camera retro',
            '排序依據：網友穿搭數 > 評論數 > 評分 > 上架時間'
        );
    }

    public function getNew(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getNewHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '新款商品',
            'positive',
            'leaf',
            '排序依據：特價幅度 > 評論數 > 評分 > 上架時間'
        );
    }

    public function getComingSoon(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getComingSoonHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '即將上市商品',
            'coming-soon',
            'checked calendar',
            '排序依據：特價幅度 > 評論數 > 評分 > 上架時間'
        );
    }

    public function getMultiBuy(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getMultiBuyHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '合購商品',
            'info',
            'cubes',
            '排序依據：評論數 > 評分 > 上架時間'
        );
    }

    public function getOnlineSpecial(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getOnlineSpecialHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '網路獨家販售商品',
            'online-special',
            'tv',
            '排序依據：特價幅度 > 評論數 > 評分 > 上架時間'
        );
    }

    private function getList(
        $hmallProducts,
        $listRequest,
        $typeName,
        $typeStyle,
        $typeIcon,
        $description
    ) {
        $count = count($hmallProducts);
        $hmallProductList = $this->service->divideHmallProducts($hmallProducts, $listRequest->brand);

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => $typeName,
            'typeStyle' => $typeStyle,
            'typeIcon' => $typeIcon,
            'description' => $description,
        ]);
    }
}
