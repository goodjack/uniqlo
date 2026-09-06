<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     * code 精準符合的商品才是這組編號真正的商品頁，排序上要贏過只是
     * name 裡帶到這組號碼的其他商品頁。
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
     * 48251 只是 482514 的前綴，不是這個號碼本身，不該命中。
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
        $response->assertDontSee('AIRism');
        $response->assertSee('0 件');
    }

    public function test_keyword_query_no_longer_redirects_to_google(): void
    {
        $response = $this->get(route('search.index', ['query' => '羽絨']));

        $response->assertRedirect(route('search.show', ['query' => '羽絨']));
    }

    /**
     * 搜尋表單走 index，但使用者可以直接開 /search/{query}。
     * 只在 index 擋長度，等於沒有擋。
     */
    public function test_an_over_long_query_is_rejected_on_the_direct_url_too(): void
    {
        $tooLong = str_repeat('羽', 101);

        $this->get(route('search.index', ['query' => $tooLong]))->assertSessionHasErrors('query');
        $this->get(route('search.show', ['query' => $tooLong]))->assertNotFound();
    }

    /**
     * 超過上限的關鍵字被丟掉時，畫面要講出來，不然結果會莫名其妙地變多。
     */
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
