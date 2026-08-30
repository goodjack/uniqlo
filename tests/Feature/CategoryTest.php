<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCategory('all_women', '女裝', null, CategoryLevel::Top);
        $this->createCategory('all_women-tops', '上衣類', 'all_women', CategoryLevel::One);
        $this->createCategory('all_women-tops-tshirt', 'T恤', 'all_women-tops', CategoryLevel::Two);
        $this->createCategory('all_women-tops-anchor01', '女裝/男女適穿', 'all_women-tops', CategoryLevel::Three);
        $this->createCategory('all_women-empty', '沒有商品的分類', 'all_women', CategoryLevel::One);
    }

    public function test_overview_lists_categories_that_still_have_products(): void
    {
        $this->attachProduct($this->createProduct(['name' => '短袖上衣']), 'all_women-tops');

        $response = $this->get(route('categories.index'));

        $response->assertOk();
        $response->assertSee('女裝');
        $response->assertSee('上衣類');
        $response->assertDontSee('沒有商品的分類');
    }

    public function test_category_page_shows_its_products_and_child_categories(): void
    {
        $this->attachProduct($this->createProduct(['name' => '短袖上衣']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['name' => '長袖T恤']), 'all_women-tops-tshirt');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']));

        $response->assertOk();
        $response->assertSee('短袖上衣');
        // 子分類要列出來，讓使用者往下鑽
        $response->assertSee('T恤');
    }

    /**
     * 分類主檔只增不減，商品全部下架的分類仍留在表裡，不能讓它變成空頁面。
     */
    public function test_category_without_available_products_returns_404(): void
    {
        $soldOut = $this->createProduct(['name' => '已售完', 'stock' => 'N']);
        $this->attachProduct($soldOut, 'all_women-empty');

        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-empty']))->assertNotFound();
    }

    /**
     * 只有大類與品項開頁面：頂層太廣，錨點那層的名稱當標題不成句。
     */
    public function test_only_level_one_and_two_have_pages(): void
    {
        $this->attachProduct($this->createProduct(), 'all_women');
        $this->attachProduct($this->createProduct(), 'all_women-tops-anchor01');

        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women']))->assertNotFound();
        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops-anchor01']))->assertNotFound();
    }

    /**
     * 分類屬於哪一家是由它底下的商品決定的，網址的品牌對不上就不是同一個資源。
     */
    public function test_a_category_is_not_reachable_under_the_wrong_brand(): void
    {
        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO']), 'all_women-tops');

        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))->assertOk();
        $this->get(route('categories.show', ['brand' => 'gu', 'code' => 'all_women-tops']))->assertNotFound();
    }

    public function test_unknown_category_code_returns_404(): void
    {
        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'does-not-exist']))->assertNotFound();
    }

    /**
     * 兩家的分類 code 各成一套（UNIQLO 是 all_women-tops、GU 是 women_all），
     * 名稱又會撞，所以總覽要標出這是誰的分類。
     */
    public function test_overview_labels_which_brand_each_group_belongs_to(): void
    {
        $this->createCategory('women_all', 'WOMEN', null, CategoryLevel::Top, 'GU');
        $this->createCategory('women_knitandcardigan', '針織上衣', 'women_all', CategoryLevel::One, 'GU');

        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['brand' => 'GU']), 'women_knitandcardigan');

        $response = $this->get(route('categories.index'));

        $response->assertOk();
        $response->assertSee('UNIQLO');
        $response->assertSee('GU');
        $response->assertSee('針織上衣');
    }

    /**
     * 分類本身有商品時，帶著任何未知的 query string 進來都不該變成 404。
     */
    public function test_an_unknown_query_string_does_not_turn_the_page_into_404(): void
    {
        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO', 'name' => '短袖上衣']), 'all_women-tops');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?ref=old');

        $response->assertOk();
        $response->assertSee('短袖上衣');
    }

    /**
     * 官方在該分類內的排序權重決定顯示順序，出來就跟官網一致。
     */
    public function test_products_follow_the_official_sort_within_the_category(): void
    {
        $this->attachProduct($this->createProduct(['name' => '排在後面的']), 'all_women-tops', '006002009');
        $this->attachProduct($this->createProduct(['name' => '排在前面的']), 'all_women-tops', '006002001');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']));

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, '排在後面的'),
            strpos($content, '排在前面的'),
        );
    }

    /**
     * 從 SEO 直接進分類頁的人需要知道自己在整棵樹的哪裡。
     * 頂層沒有自己的頁面，所以只當文字不做連結。
     */
    public function test_category_page_shows_a_breadcrumb_back_to_the_root(): void
    {
        $this->attachProduct($this->createProduct(), 'all_women-tops-tshirt');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops-tshirt']));

        $response->assertOk();
        $response->assertSee('商品分類');
        $response->assertSee('女裝');
        $response->assertSee(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']));
    }

    /**
     * 男女適穿的商品會在男裝與女裝樹下各掛一份同名分類，
     * 商品頁只需要顯示一次。
     */
    public function test_product_page_lists_its_categories_without_duplicates(): void
    {
        $this->createCategory('all_men-tops', '上衣類', 'all_women', CategoryLevel::One);
        $this->createCategory('all_men-tops-tshirt', 'T恤', 'all_men-tops', CategoryLevel::Two);

        $product = $this->createProduct(['product_code' => 'u778899']);
        $this->attachProduct($product, 'all_women-tops-tshirt');
        $this->attachProduct($product, 'all_men-tops-tshirt');

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u778899']))
            ->assertOk()
            ->getContent();

        // 兩個分類都叫「T恤」，只該連到其中一個，不是兩個都列
        $linksToWomen = str_contains($content, 'categories/uniqlo/all_women-tops-tshirt');
        $linksToMen = str_contains($content, 'categories/uniqlo/all_men-tops-tshirt');

        $this->assertTrue($linksToWomen || $linksToMen, '商品頁應該列出所屬分類');
        $this->assertFalse($linksToWomen && $linksToMen, '同名的分類只該出現一次');
    }

    private function createCategory(
        string $code,
        string $name,
        ?string $parent,
        CategoryLevel $level,
        string $brand = 'UNIQLO'
    ): HmallCategory {
        return HmallCategory::create([
            'brand' => $brand,
            'code' => $code,
            'name' => $name,
            'parent_code' => $parent,
            'level' => $level->value,
        ]);
    }

    private function createProduct(array $attributes = []): HmallProduct
    {
        static $sequence = 0;
        $sequence++;

        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => "測試商品 {$sequence}",
            'code' => "45000{$sequence}",
            'product_code' => "u45000{$sequence}",
            'sex' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
        ], $attributes)));
    }

    private function attachProduct(HmallProduct $product, string $categoryCode, string $sort = '001'): void
    {
        $category = HmallCategory::where('brand', $product->brand)
            ->where('code', $categoryCode)
            ->firstOrFail();

        $product->categories()->attach($category->id, ['sort' => $sort]);
    }
}
