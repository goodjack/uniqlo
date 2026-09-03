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
            '依特價幅度排序'
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
            '依特價幅度排序'
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
            '依評論數排序'
        );
    }

    public function getJapanMostReviewed(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getJapanMostReviewedHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '日本熱門評論商品',
            'most-reviewed',
            'comments outline',
            '依日本評論數排序，卡片上顯示的是日本的評分與評論數',
            true,
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
            '依網友穿搭數排序'
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
            '依特價幅度排序'
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
            '依特價幅度排序'
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
            '依評論數排序'
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
            '依特價幅度排序'
        );
    }

    public function getMostVisited(ListRequest $listRequest)
    {
        $hmallProducts = $this->service->getMostVisitedHmallProducts();

        return $this->getList(
            $hmallProducts,
            $listRequest,
            '熱門瀏覽商品',
            'most-visited',
            'chart line',
            '依瀏覽次數排序',
        );
    }

    /**
     * 每一種清單共用的取資料與渲染。
     *
     * $sortSummary 是「預設排序是照什麼排的」一句話，進 slate 的副標
     * （65 件，依特價幅度排序）。原本這裡傳的是「排序依據：特價幅度 > 評論數 >
     * 評分 > 上架時間」那種完整權重鏈，寫在標題底下佔一整行，而且使用者選了
     * 價格排序之後它還在講預設排序，是錯的——所以只留預設排序的說法，實際
     * 排序由 blade 依 sort 參數決定要不要換句話。
     */
    private function getList(
        $hmallProducts,
        $listRequest,
        $typeName,
        $typeStyle,
        $typeIcon,
        $sortSummary,
        $useJapanRating = false
    ) {
        $hmallProducts = $this->service->filterHmallProducts($hmallProducts, $listRequest);
        $hmallProducts = $this->service->sortHmallProducts($hmallProducts, $listRequest);
        $count = count($hmallProducts);

        $hmallProductList = $this->service->groupHmallProducts($hmallProducts);

        return view('lists.list', [
            'hmallProductList' => $hmallProductList,
            'count' => $count,
            'typeName' => $typeName,
            'typeStyle' => $typeStyle,
            'typeIcon' => $typeIcon,
            'sortSummary' => $sortSummary,
            'useJapanRating' => $useJapanRating,
        ]);
    }
}
