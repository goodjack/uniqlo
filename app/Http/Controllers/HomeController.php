<?php

namespace App\Http\Controllers;

use App\Services\ListService;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    /**
     * 首頁每個區塊顯示幾張卡片。橫向捲動一次露出 2 到 6 張（依螢幕寬），
     * 12 張約等於滑動兩到三次，再多使用者不會捲完。
     */
    private const PRODUCTS_PER_SECTION = 12;

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
     * 四組資料全部來自 ListService 既有的預熱快取，首頁不額外查資料庫。
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
            // 快取尚未預熱或該清單當天沒有商品時，整個區塊不出現，而不是留一排空白。
            ->filter(fn (array $section) => $section['products']->isNotEmpty())
            ->values();
    }
}
