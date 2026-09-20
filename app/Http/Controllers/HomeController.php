<?php

namespace App\Http\Controllers;

use App\Services\ListService;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    /**
     * 首頁每個區塊顯示幾張卡片。桌機是 .ts.doubling.link.cards.six，一排剛好
     * 六張放滿，不用再往下捲；手機 doubling 成兩欄，一區塊三排。
     */
    private const PRODUCTS_PER_SECTION = 6;

    protected $listService;

    public function __construct(ListService $listService)
    {
        $this->listService = $listService;
    }

    public function index()
    {
        return view('home', [
            'sections' => $this->getSections(),
        ]);
    }

    /**
     * 首頁的商品區塊，順序即畫面由上而下的順序。
     *
     * 熱門瀏覽排第一：那份排名是這個站自己的流量資料，站內其他頁面都看不到，
     * 其餘三組在對應的清單頁都找得到。
     *
     * 四組資料走的是 HmallProductRepository 的 Cache::has() 模式：平時排程
     * 已經預熱好，首頁只讀快取；但快取沒暖（cache:clear 之後、換新機器部署、
     * 或某支查詢從沒成功寫入過）時，第一個打進來的人會就地跑那支查詢，沒有
     * 互斥鎖——同一時間有幾個人打進來，就會有幾份人各自跑一份相同的查詢，
     * 其中 top-wearing 那支還帶一個 style_hint_items 的 group-by 子查詢 join。
     */
    private function getSections(): Collection
    {
        $definitions = [
            [
                'title' => '大家都在看',
                'subtitle' => '最多人瀏覽的商品',
                'route' => 'lists.most-visited',
                'style' => 'most-visited',
                'icon' => 'chart line',
                'products' => fn () => $this->listService->getMostVisitedHmallProducts(),
            ],
            [
                'title' => '限時特價',
                'subtitle' => '期間限定的優惠價格',
                'route' => 'lists.limited-offers',
                'style' => 'negative',
                'icon' => 'certificate',
                'products' => fn () => $this->listService->getLimitedOfferHmallProducts(),
            ],
            [
                'title' => '新品上市',
                'subtitle' => '最近上架的商品',
                'route' => 'lists.new',
                'style' => 'positive',
                'icon' => 'leaf',
                'products' => fn () => $this->listService->getNewHmallProducts(),
            ],
            [
                'title' => '熱門穿搭',
                'subtitle' => '最多人搭配的商品',
                'route' => 'lists.top-wearing',
                'style' => 'top-wearing',
                'icon' => 'camera retro',
                'products' => fn () => $this->listService->getTopWearingHmallProducts(),
            ],
        ];

        return collect($definitions)
            ->map(function (array $definition) {
                $products = ($definition['products'])();

                return [
                    ...$definition,
                    'products' => collect($products)->take(self::PRODUCTS_PER_SECTION),
                ];
            })
            // 該清單當天沒有商品，或「大家都在看」跟 GA 拿資料失敗退回空集合時
            // （見 HmallProductRepository::setMostVisitedHmallProductsCache()），
            // 整個區塊不出現，而不是留一排空白。
            ->filter(fn (array $section) => $section['products']->isNotEmpty())
            ->values();
    }
}
