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

        // 「熱門穿搭」也是導覽列的清單名稱，只看字串壞掉也會過，要看區塊標題
        $headings = $this->sectionHeadings($response->getContent());

        foreach (self::SECTION_TITLES as $title) {
            $this->assertContains($title, $headings);
        }
    }

    /**
     * 首頁用 Tocas 原生的六格 grid。不掛 Tocas 的 link：它讓整張卡片 hover
     * 浮起，滑到收藏鈕上時看起來像整張卡在反應。
     */
    public function test_each_section_uses_the_six_column_grid(): void
    {
        $this->mockListService();

        $response = $this->get(route('home'));

        $this->assertGreaterThan(
            0,
            substr_count($response->getContent(), 'ts doubling cards six uq-product-cards'),
            '首頁每個區塊都該是 ts doubling cards six uq-product-cards'
        );
        $response->assertDontSee('home-product-row');
    }

    public function test_each_section_links_to_its_list_page(): void
    {
        $this->mockListService();

        $response = $this->get(route('home'));

        // 這幾個清單網址在導覽列與頁尾每頁都有，要看區塊自己的「看全部」
        $this->assertSame([
            route('lists.most-visited'),
            route('lists.limited-offers'),
            route('lists.new'),
            route('lists.top-wearing'),
        ], $this->xpathValues($response->getContent(), '//a[contains(@class, "uq-header-action")]/@href'));
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

        // 第一區塊截到 6 張，其餘三區塊各 2 張
        $this->assertSame(12, substr_count($response->getContent(), 'hmall-products/'));
    }

    /**
     * @param  array<string, int>  $counts  覆寫個別清單的商品數，預設每個清單 2 筆
     */
    /**
     * 導覽列與頁尾在每一頁，多數頁面不經過 ListRequest：連結帶的品牌要跟清單頁
     * 同一套規則（只認網址參數、只收 UNIQLO／GU），不合法或內文的值不能帶進連結。
     */
    public function test_nav_and_footer_links_carry_only_a_valid_brand_from_the_query_string(): void
    {
        $this->mockListService();

        $this->get(route('home', ['brand' => 'GU']))
            ->assertSee(route('lists.sale', ['brand' => 'GU']));

        $this->get(route('home', ['brand' => 'junk']))
            ->assertDontSee('brand=junk');

        $this->call('GET', route('home'), [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['brand' => 'GU']))
            ->assertOk()
            ->assertDontSee('brand=GU');
    }

    /**
     * @return array<int, string>
     */
    private function sectionHeadings(string $html): array
    {
        // 區塊標題的文字節點（不含副標）
        return array_map('trim', $this->xpathValues(
            $html,
            '//h2[a[contains(@class, "uq-header-action")]]/text()[normalize-space()]'
        ));
    }

    /**
     * @return array<int, string>
     */
    private function xpathValues(string $html, string $expression): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return array_map(
            fn ($node) => $node->nodeValue,
            iterator_to_array((new \DOMXPath($dom))->query($expression))
        );
    }

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
