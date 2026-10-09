<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Support\Url;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createProduct(['name' => '特級極輕羽絨外套', 'code' => '450001', 'product_code' => 'u450001']);
        $this->createProduct(['name' => 'AIRism 圓領T恤(短袖)', 'code' => '450002', 'product_code' => 'u450002']);
        $this->createProduct(['name' => 'HEATTECH 圓領T恤(長袖)', 'code' => '450003', 'product_code' => 'u450003']);
        $this->createProduct([
            'name' => '寬鬆工作短褲',
            'code' => '450004',
            'product_code' => 'u450004',
            'product_name' => '男裝 100% 純棉工作短褲',
        ]);
    }

    /**
     * 「外套」這種詞常常只出現在分類名稱、不在品名裡，搜不到會很怪。
     */
    public function test_matches_against_the_categories_a_product_belongs_to(): void
    {
        $category = HmallCategory::create([
            'brand' => 'UNIQLO',
            'code' => 'all_women-outer',
            'name' => '外套類',
            'parent_code' => null,
            'level' => CategoryLevel::One->value,
        ]);

        HmallProduct::where('code', '450001')->firstOrFail()
            ->categories()->attach($category->id, ['sort' => '001']);

        $response = $this->get(route('search.show', ['query' => '外套類']));

        $response->assertOk();
        // 這件商品叫「特級極輕羽絨外套」，靠分類命中的是沒有「外套類」三個字的其他件
        $response->assertSee('特級極輕羽絨外套');
        $response->assertDontSee('寬鬆工作短褲');
    }

    public function test_keyword_search_returns_matching_products(): void
    {
        $response = $this->get(route('search.show', ['query' => '羽絨']));

        $response->assertOk();
        $response->assertSee('特級極輕羽絨外套');
        $response->assertDontSee('寬鬆工作短褲');
    }

    /**
     * 多個關鍵字是 AND：每個詞都要命中才算符合。
     */
    public function test_every_keyword_must_match(): void
    {
        $response = $this->get(route('search.show', ['query' => '圓領 長袖']));

        $response->assertOk();
        $response->assertSee('HEATTECH');
        // 圓領有命中但長袖沒有，所以短袖那件不該出現
        $response->assertDontSee('AIRism');
    }

    public function test_matches_against_product_name_as_well_as_name(): void
    {
        $response = $this->get(route('search.show', ['query' => '純棉']));

        $response->assertOk();
        $response->assertSee('寬鬆工作短褲');
    }

    /**
     * 使用者打進來的 % 與 _ 要當成一般文字，不能變成萬用字元。
     */
    public function test_like_wildcards_in_the_query_are_escaped(): void
    {
        // 只有 product_name 寫著「100% 純棉」那件真的含有 %，其餘都不該被撈出來
        $response = $this->get(route('search.show', ['query' => '%']));

        $response->assertOk();
        $response->assertSee('寬鬆工作短褲');
        $response->assertDontSee('特級極輕羽絨外套');
        $response->assertDontSee('AIRism');

        // 測試資料裡沒有任何底線，所以 _ 當字面字元查應該完全沒有結果
        $this->get(route('search.show', ['query' => '_']))
            ->assertOk()
            ->assertSee('找不到符合的商品');
    }

    public function test_numeric_query_with_single_hit_redirects_to_the_product_page(): void
    {
        $response = $this->get(route('search.index', ['query' => '450001']));

        $response->assertRedirect(
            HmallProduct::where('code', '450001')->firstOrFail()->route_url
        );
    }

    /**
     * 查無貨號時要退回關鍵字搜尋，使用者才看得到空狀態與 Google 搜尋的出口。
     */
    public function test_a_leading_zero_code_falls_back_to_keyword_search_with_an_empty_state(): void
    {
        $response = $this->get(route('search.show', ['query' => '0450001']));

        $response->assertOk();
        $response->assertDontSee('商品編號 0450001');
        $response->assertSee('找不到符合的商品');
        $response->assertSee('改用 Google 搜尋');
    }

    /**
     * UNIQLO 常把多個貨號共用同一個商品頁：這件商品的 code 是 482516，
     * 但 name 裡列著這頁涵蓋的另外三個號碼，其中一個是搜尋字。
     */
    public function test_a_shared_item_code_with_a_single_hit_redirects_to_the_product_page(): void
    {
        $sharedProduct = $this->createProduct([
            'name' => 'AIRism 圓領T恤(短袖) 474238 / 482514 / 474236',
            'code' => '482516',
            'product_code' => 'u482516',
        ]);

        $response = $this->get(route('search.index', ['query' => '482514']));

        $response->assertRedirect($sharedProduct->route_url);
    }

    public function test_shared_item_code_search_page_shows_the_product_and_a_hint(): void
    {
        $this->createProduct([
            'name' => 'AIRism 圓領T恤(短袖) 474238 / 482514 / 474236',
            'code' => '482516',
            'product_code' => 'u482516',
        ]);

        $response = $this->get(route('search.show', ['query' => '482514']));

        $response->assertOk();
        $response->assertSee('AIRism 圓領T恤(短袖)');
        $response->assertSee('此商品頁同時包含貨號 474238 / 482514 / 474236');
    }

    /**
     * code 精準符合的才是這組編號真正的商品頁。
     */
    public function test_a_precise_code_match_sorts_before_a_shared_number_match(): void
    {
        $this->createProduct([
            'name' => 'AIRism 圓領T恤(短袖) 474238 / 482514 / 474236',
            'code' => '482516',
            'product_code' => 'u482516',
        ]);
        $this->createProduct([
            'name' => '合身襯衫',
            'code' => '482514',
            'product_code' => 'u482514',
        ]);

        $content = $this->get(route('search.show', ['query' => '482514']))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($content, 'AIRism'),
            strpos($content, '合身襯衫'),
        );
    }

    /**
     * 48251 只是 482514 的前綴，不算這個貨號；關鍵字搜尋本來就會用子字串撈到它。
     */
    public function test_a_number_that_is_only_a_prefix_of_another_code_does_not_match(): void
    {
        $this->createProduct([
            'name' => 'AIRism 圓領T恤(短袖) 474238 / 482514 / 474236',
            'code' => '482516',
            'product_code' => 'u482516',
        ]);

        $response = $this->get(route('search.show', ['query' => '48251']));

        $response->assertOk();
        $response->assertDontSee('商品編號 48251');
        $response->assertSee('「48251」');
    }

    /**
     * 短數字常出現在品名（2WAY、100%），照編號比對會撈出整串沒有分頁的結果。
     */
    public function test_a_short_number_uses_paginated_keyword_search(): void
    {
        $this->createProduct(['name' => '2WAY 托特包', 'code' => '450010', 'product_code' => 'u450010']);

        $response = $this->get(route('search.show', ['query' => '2']));

        $response->assertOk();
        $response->assertViewHas('isProductCodeSearch', false);
        $response->assertViewHas('hmallProducts', fn ($products) => $products instanceof LengthAwarePaginator);
    }

    public function test_a_short_number_with_a_single_hit_does_not_redirect(): void
    {
        $this->createProduct(['name' => '2WAY 托特包', 'code' => '450010', 'product_code' => 'u450010']);

        $this->get(route('search.index', ['query' => '2']))
            ->assertOk()
            ->assertSee('2WAY 托特包');
    }

    /**
     * 小數點在 REGEXP 裡是萬用字元，不能把 123.456 當貨號查。
     */
    public function test_a_query_with_a_decimal_point_does_not_redirect_straight_to_a_product_page(): void
    {
        $this->createProduct([
            'name' => '限定聯名款 123/456',
            'code' => '999001',
            'product_code' => 'u999001',
        ]);

        $this->get(route('search.index', ['query' => '123.456']))
            ->assertOk()
            ->assertSee('「123.456」的搜尋結果');
    }

    public function test_a_query_with_a_decimal_point_finds_nothing_via_keyword_search(): void
    {
        $this->createProduct([
            'name' => '限定聯名款 123/456',
            'code' => '999001',
            'product_code' => 'u999001',
        ]);

        $response = $this->get(route('search.show', ['query' => '123.456']));

        $response->assertOk();
        $response->assertDontSee('限定聯名款');
        $response->assertSee('找不到符合的商品');
    }

    public function test_the_search_form_shows_keyword_results_without_redirecting(): void
    {
        $this->get(route('search.index', ['query' => '羽絨']))
            ->assertOk()
            ->assertSee('特級極輕羽絨外套')
            ->assertDontSee('寬鬆工作短褲');
    }

    /**
     * 商品名與分類名常帶這些字元（「休閒長褲 469930 / 475382」「男裝/男女適穿」），
     * 使用者直接複製貼上就會碰到。
     */
    #[DataProvider('queriesWithUnusualCharacters')]
    public function test_queries_with_unusual_characters_reach_the_matching_product(string $query, string $name): void
    {
        $this->createProduct(['name' => $name, 'code' => '460001', 'product_code' => 'u460001']);

        $this->get(route('search.index', ['query' => $query]))
            ->assertOk()
            ->assertSee($name);

        $this->get(route('search.show', ['query' => Url::segment($query)]))
            ->assertOk()
            ->assertSee($name);
    }

    public static function queriesWithUnusualCharacters(): array
    {
        return [
            'slash' => ['469930 / 475382', '休閒長褲 469930 / 475382'],
            'slash inside a word' => ['T恤/短袖', '印花T恤/短袖'],
            'hash' => ['C#', 'C#聯名T恤'],
            'question mark' => ['why?', 'why?系列帽T'],
            'percent' => ['100%', '男裝 100% 純棉工作短褲'],
            'encoded-looking percent' => ['a%20b', '標籤 a%20b 測試'],
        ];
    }

    /**
     * /search/keywords 是 Google 站內搜尋頁，搜「keywords」這個字不能被帶過去。
     */
    public function test_searching_for_the_word_keywords_does_not_land_on_google_search(): void
    {
        $this->createProduct(['name' => 'keywords 印花T恤', 'code' => '460002', 'product_code' => 'u460002']);

        $this->get(route('search.index', ['query' => 'keywords']))
            ->assertOk()
            ->assertSee('keywords 印花T恤')
            ->assertDontSee('gcse-searchbox', false);
    }

    public function test_pagination_from_the_search_form_keeps_the_query(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createProduct(['name' => "羽絨背心 {$i}", 'code' => "47{$i}", 'product_code' => "u47{$i}"]);
        }

        $this->get(route('search.index', ['query' => '羽絨背心']))
            ->assertOk()
            ->assertSee('query=%E7%BE%BD%E7%B5%A8%E8%83%8C%E5%BF%83&amp;page=2', false);

        $this->get(route('search.index', ['query' => '羽絨背心', 'page' => 2]))
            ->assertOk()
            ->assertSee('羽絨背心');
    }

    /**
     * 驗證與取值要讀同一個來源：GET 只帶 JSON 內文時，網址參數是空的。
     */
    public function test_a_get_request_with_only_a_json_body_is_rejected_instead_of_crashing(): void
    {
        $this->call(
            'GET',
            route('search.index'),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['query' => '羽絨']),
        )->assertUnprocessable();
    }

    /**
     * 表單送出一次搜尋只能扣一次額度（以前會轉址到 /search/{query} 再扣一次）。
     */
    public function test_a_search_from_the_form_counts_once_against_the_limit(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->followingRedirects()
                ->get(route('search.index', ['query' => '羽絨']))
                ->assertOk();
        }

        $this->get(route('search.index', ['query' => '羽絨']))->assertTooManyRequests();
    }

    public function test_loading_favorites_does_not_use_up_the_search_limit(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson(route('favorites.cards'), ['items' => [['brand' => 'UNIQLO', 'code' => 'u450001']]])
                ->assertOk();
        }

        $this->get(route('search.index', ['query' => '羽絨']))->assertOk();
        $this->get(route('search.show', ['query' => '羽絨']))->assertOk();
    }

    public function test_an_over_long_query_is_rejected_on_the_direct_url_too(): void
    {
        $tooLong = str_repeat('羽', 101);

        $this->get(route('search.index', ['query' => $tooLong]))->assertSessionHasErrors('query');
        $this->get(route('search.show', ['query' => $tooLong]))->assertNotFound();
    }

    public function test_keywords_beyond_the_limit_are_reported_to_the_user(): void
    {
        $response = $this->get(route('search.show', ['query' => '一 二 三 四 五 六 七']));

        $response->assertOk();
        $response->assertSee('沒有用到');
        $response->assertSee('六');
        $response->assertSee('七');
    }

    public function test_search_results_are_not_indexed(): void
    {
        $this->get(route('search.show', ['query' => '羽絨']))
            ->assertSee('noindex', false);
    }

    private function createProduct(array $attributes): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
            'evaluation_count' => 0,
            'score' => 0,
        ], $attributes)));
    }
}
