<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\HmallProductService;
use App\Services\ListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * GA 事件要的資料由伺服器寫在 data 屬性，public/js/analytics.js 讀出來送。
 * 來源代號一旦上線就是報表裡的維度值，改名會跟舊資料對不起來，這裡逐一釘住。
 */
class AnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_XPATH = '//*[@data-analytics-list="%s"]//*[@data-analytics-item]/@data-analytics-item';

    public function test_every_page_loads_the_tracking_script_before_favorites(): void
    {
        $content = $this->get(route('pages.changelog'))->assertOk()->getContent();

        $analytics = strpos($content, 'js/analytics.js');
        $favorites = strpos($content, 'js/favorites.js');

        $this->assertNotFalse($analytics);
        $this->assertLessThan($favorites, $analytics, 'favorites.js 送收藏事件時要用到 analytics.js');
    }

    public function test_a_category_card_carries_the_ga_item_and_its_source(): void
    {
        $this->seedCategory();
        $product = $this->createProduct(['product_code' => 'u0000000053204', 'name' => '短袖上衣', 'code' => '990001', 'min_price' => 590]);
        $product->categories()->attach(HmallCategory::where('code', 'all_women-tops')->value('id'), ['sort' => '001']);

        $content = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        $this->assertSame([[
            'item_id' => 'UNIQLO:u0000000053204',
            'item_name' => '短袖上衣 990001',
            'item_brand' => 'UNIQLO',
            'price' => 590,
        ]], $this->itemsIn($content, 'category'));
    }

    public function test_home_rows_each_have_their_own_source(): void
    {
        $this->mock(ListService::class, function (MockInterface $mock) {
            foreach (['getMostVisitedHmallProducts', 'getLimitedOfferHmallProducts', 'getNewHmallProducts', 'getTopWearingHmallProducts'] as $method) {
                $mock->shouldReceive($method)->andReturn($this->fakeProducts(1));
            }
        });

        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(
            ['home_most_visited', 'home_limited_offers', 'home_new', 'home_top_wearing'],
            $this->xpathValues($content, '//*[@data-analytics-list]/@data-analytics-list')
        );
    }

    /**
     * 清單頁的四個性別段都是同一個來源。
     */
    public function test_a_list_page_names_its_source_after_the_list(): void
    {
        $this->createProduct(['identity' => json_encode(['concessional_rate']), 'sex' => '男裝', 'gender' => '男裝']);

        $content = $this->get(route('lists.sale'))->assertOk()->getContent();

        $this->assertSame(['list_sale'], array_values(array_unique(
            $this->xpathValues($content, '//*[@data-analytics-list]/@data-analytics-list')
        )));
        $this->assertCount(1, $this->itemsIn($content, 'list_sale'));
    }

    public function test_the_product_page_favorite_button_and_sections_carry_analytics_data(): void
    {
        $this->createProduct(['product_code' => 'u990001', 'min_price' => 990]);
        // partialMock 不跑建構子，其餘方法會缺 repository，所以自己帶建構子參數
        $service = Mockery::mock(HmallProductService::class, [
            $this->app->make(HmallProductRepository::class),
            $this->app->make(ProductRepository::class),
        ])->makePartial();
        $service->shouldReceive('getCommonlyStyledHmallProducts')->andReturn($this->fakeProducts(1));
        $service->shouldReceive('getRelatedHmallProducts')->andReturn($this->fakeProducts(2));
        $this->instance(HmallProductService::class, $service);

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $button = $this->xpathValues($content, '//button[@data-favorite-button]/@data-analytics-item');

        $this->assertCount(1, $button);
        $this->assertSame('UNIQLO:u990001', json_decode($button[0], true)['item_id']);
        $this->assertSame(990, json_decode($button[0], true)['price']);
        $this->assertCount(1, $this->itemsIn($content, 'product_commonly_styled'));
        $this->assertCount(2, $this->itemsIn($content, 'product_related'));
    }

    /**
     * 收藏頁不吐價格，GA 用的商品資料也不能帶。
     */
    public function test_favorite_rows_carry_the_ga_item_without_a_price(): void
    {
        $this->createProduct(['product_code' => 'u001', 'name' => '羽絨外套', 'code' => '450001', 'min_price' => 1990]);

        $content = $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 'u001']],
        ])->assertOk()->getContent();

        $this->assertSame(
            [['item_id' => 'UNIQLO:u001', 'item_name' => '羽絨外套 450001', 'item_brand' => 'UNIQLO']],
            array_map(
                fn ($json) => json_decode($json, true),
                $this->xpathValues($content, '//*[@data-analytics-item]/@data-analytics-item')
            )
        );

        $this->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('id="favorites-cards" data-analytics-list="favorites"', false);
    }

    /**
     * 站內搜尋有兩種網址，兩種都要送件數；零結果最重要。
     */
    public function test_both_search_urls_report_the_term_and_result_count(): void
    {
        $this->createProduct(['name' => '羽絨外套', 'code' => '450001', 'product_code' => 'u450001']);

        foreach ([
            route('search.index', ['query' => '羽絨']),
            route('search.show', ['query' => '羽絨']),
        ] as $url) {
            $content = $this->get($url)->assertOk()->getContent();

            $this->assertSame(['羽絨'], $this->xpathValues($content, '//*[@data-analytics-search-term]/@data-analytics-search-term'), $url);
            $this->assertSame(['1'], $this->xpathValues($content, '//*[@data-analytics-result-count]/@data-analytics-result-count'), $url);
            $this->assertCount(1, $this->itemsIn($content, 'search_keyword'), $url);
        }

        $content = $this->get(route('search.show', ['query' => '找不到的東西']))->assertOk()->getContent();

        $this->assertSame(['0'], $this->xpathValues($content, '//*[@data-analytics-result-count]/@data-analytics-result-count'));
    }

    public function test_turning_the_page_does_not_count_as_another_search(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createProduct(['name' => "羽絨背心 {$i}", 'code' => "47{$i}", 'product_code' => "u47{$i}"]);
        }

        $content = $this->get(route('search.index', ['query' => '羽絨背心', 'page' => 2]))->assertOk()->getContent();

        $this->assertSame([], $this->xpathValues($content, '//*[@data-analytics-search-term]/@data-analytics-search-term'));
        $this->assertCount(1, $this->itemsIn($content, 'search_keyword'));
    }

    public function test_a_product_code_search_reports_its_own_source(): void
    {
        $this->createProduct(['code' => '450001', 'product_code' => 'u450001']);
        $this->createProduct(['code' => '450001', 'product_code' => 'g450001', 'brand' => 'GU']);

        $content = $this->get(route('search.show', ['query' => '450001']))->assertOk()->getContent();

        $this->assertSame(['2'], $this->xpathValues($content, '//*[@data-analytics-result-count]/@data-analytics-result-count'));
        $this->assertCount(2, $this->itemsIn($content, 'search_product_code'));
    }

    public function test_the_privacy_policy_mentions_google_analytics(): void
    {
        $this->get(route('pages.privacy-policy'))
            ->assertOk()
            ->assertSee('本網站使用 Google Analytics');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsIn(string $html, string $list): array
    {
        return array_map(
            fn ($json) => json_decode($json, true),
            $this->xpathValues($html, sprintf(self::ITEM_XPATH, $list))
        );
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

    private function createProduct(array $attributes = []): HmallProduct
    {
        static $sequence = 0;
        $sequence++;

        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => "測試商品 {$sequence}",
            'code' => "9900{$sequence}",
            'product_code' => "u9900{$sequence}",
            'sex' => '女裝',
            'gender' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
            'lowest_record_price' => 990,
            'highest_record_price' => 990,
        ], $attributes)));
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
                'identity' => '[]',
                'stock' => 'Y',
                'min_price' => 990,
            ]));

            $product->setRelation('japanProduct', null);

            return $product;
        });
    }

    private function seedCategory(): void
    {
        foreach ([['all_women', null, CategoryLevel::Top], ['all_women-tops', 'all_women', CategoryLevel::One]] as [$code, $parent, $level]) {
            HmallCategory::create([
                'brand' => 'UNIQLO',
                'code' => $code,
                'name' => $code,
                'parent_code' => $parent,
                'level' => $level->value,
            ]);
        }
    }
}
