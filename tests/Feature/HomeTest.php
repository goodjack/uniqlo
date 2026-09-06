<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use App\Services\ListService;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Tests\TestCase;

class HomeTest extends TestCase
{
    private const SECTION_TITLES = ['限時特價', '新品上市', '熱門穿搭', '大家都在看'];

    public function test_home_page_renders_all_product_sections(): void
    {
        $this->mockListService();

        $response = $this->get(route('home'));

        $response->assertOk();

        foreach (self::SECTION_TITLES as $title) {
            $response->assertSee($title);
        }
    }

    /**
     * 第三輪 UI 把首頁從橫向捲動列改回 master 的桌機六格 grid，手機靠
     * doubling 自然變兩格；這裡釘住區塊用的是哪個 Tocas class 組合，
     * 不讓它又被換回自訂的橫向捲動版型。
     */
    public function test_each_section_uses_the_six_column_grid(): void
    {
        $this->mockListService();

        $response = $this->get(route('home'));

        $this->assertGreaterThan(
            0,
            substr_count($response->getContent(), 'ts doubling link cards six'),
            '首頁每個區塊都該是 ts doubling link cards six'
        );
        $response->assertDontSee('home-product-row');
    }

    public function test_each_section_links_to_its_list_page(): void
    {
        $this->mockListService();

        $response = $this->get(route('home'));

        $response->assertSee(route('lists.limited-offers'));
        $response->assertSee(route('lists.new'));
        $response->assertSee(route('lists.top-wearing'));
        $response->assertSee(route('lists.most-visited'));
    }

    public function test_empty_section_is_hidden_instead_of_rendering_an_empty_row(): void
    {
        $this->mockListService(['getNewHmallProducts' => 0]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('新品上市');
        $response->assertSee('限時特價');
    }

    public function test_section_shows_at_most_six_cards(): void
    {
        $this->mockListService(['getLimitedOfferHmallProducts' => 30]);

        $response = $this->get(route('home'));

        // 每張卡片一個 route_url 連結，第一個區塊被截到桌機一排的 6 張，
        // 其餘三個區塊各 2 張，總共 12 個商品連結。
        $this->assertSame(12, substr_count($response->getContent(), 'hmall-products/'));
    }

    /**
     * @param  array<string, int>  $counts  覆寫個別清單的商品數，預設每個清單 2 筆
     */
    private function mockListService(array $counts = []): void
    {
        $methods = [
            'getLimitedOfferHmallProducts',
            'getNewHmallProducts',
            'getTopWearingHmallProducts',
            'getMostVisitedHmallProducts',
        ];

        $this->mock(ListService::class, function (MockInterface $mock) use ($methods, $counts) {
            foreach ($methods as $method) {
                $mock->shouldReceive($method)
                    ->andReturn($this->fakeProducts($counts[$method] ?? 2));
            }
        });
    }

    private function fakeProducts(int $count): Collection
    {
        return Collection::times($count, function (int $index) {
            $product = HmallProduct::unguarded(fn () => new HmallProduct([
                'brand' => 'UNIQLO',
                'name' => "測試商品 {$index}",
                'code' => "45500{$index}",
                'product_code' => "u000000000{$index}",
                'sex' => '男裝',
                'main_first_pic' => '/tw/test.jpg',
                // 卡片的 is_* accessor 全部走 json_decode(identity)，給空陣列才不會爆
                'identity' => '[]',
                'stock' => 'Y',
                'min_price' => 990,
            ]));

            // 卡片會讀 japanProduct 判斷要不要顯示影片 icon，先塞好關聯免得打資料庫
            $product->setRelation('japanProduct', null);

            return $product;
        });
    }
}
