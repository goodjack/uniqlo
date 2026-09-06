<?php

namespace Tests\Unit\Services;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 商品頁麵包屑要走的那一條分類路徑。
 *
 * 一件商品掛十幾個分類、官方沒有給主分類，所以規則是自己定的，這裡把三種
 * 情況釘住：選得到品項層、只剩大類、以及兩層都沒有。
 */
class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CategoryService::class);

        // 女裝那一棵：頂層 → 大類 → 品項
        $this->createCategory('all_women', '女裝', null, CategoryLevel::Top);
        $this->createCategory('all_women-tops', '上衣類', 'all_women', CategoryLevel::One);
        $this->createCategory('all_women-tops-tshirt', 'T恤', 'all_women-tops', CategoryLevel::Two);

        // 促銷用的那一棵，不屬於任何性別
        $this->createCategory('feature', '熱門推薦', null, CategoryLevel::Top);
        $this->createCategory('feature-new', '週週新品一覽', 'feature', CategoryLevel::One);
        $this->createCategory('feature-new-women', '女裝 新品一覽', 'feature-new', CategoryLevel::Two);
    }

    /**
     * 官方給「熱門推薦」那一棵的 sort 常常最小，只照 sort 選會選到促銷樹，
     * 那不是使用者想回去逛的地方。所以先過性別，再比 sort。
     */
    public function test_the_primary_category_ignores_trees_that_do_not_match_the_gender(): void
    {
        $product = $this->createProduct(['gender' => '女裝']);
        $this->attach($product, 'feature', '002001035');
        $this->attach($product, 'feature-new', '002001035');
        $this->attach($product, 'feature-new-women', '002001035');
        $this->attach($product, 'all_women', '014001999');
        $this->attach($product, 'all_women-tops', '014001999');
        $this->attach($product, 'all_women-tops-tshirt', '014001999');

        $this->assertSame('all_women-tops-tshirt', $this->service->getPrimaryCategory($product)->code);
    }

    /**
     * 有些商品官方只掛到大類，沒有品項層。
     */
    public function test_it_falls_back_to_the_level_one_category_when_there_is_no_level_two(): void
    {
        $product = $this->createProduct(['gender' => '女裝']);
        $this->attach($product, 'all_women', '014001999');
        $this->attach($product, 'all_women-tops', '014001999');

        $this->assertSame('all_women-tops', $this->service->getPrimaryCategory($product)->code);
    }

    /**
     * 只掛在頂層的商品沒有路徑可以走，麵包屑會退成「首頁 › 商品」。
     */
    public function test_it_returns_null_when_the_product_has_no_level_one_or_two_category(): void
    {
        $product = $this->createProduct(['gender' => '女裝']);
        $this->attach($product, 'all_women', '014001999');

        $this->assertNull($this->service->getPrimaryCategory($product));
    }

    /**
     * 同一棵樹底下有好幾個品項時，照官方的 sort 選。sort 是字串，
     * 不能轉成數字比——真實資料裡是 008004001008004009 這種十八位數。
     */
    public function test_it_takes_the_smallest_official_sort_within_the_matching_tree(): void
    {
        $this->createCategory('all_women-inner', '內衣類', 'all_women', CategoryLevel::One);
        $this->createCategory('all_women-inner-heattech', 'HEATTECH吸濕發熱衣', 'all_women-inner', CategoryLevel::Two);

        $product = $this->createProduct(['gender' => '女裝']);
        $this->attach($product, 'all_women', '008004001008004009');
        $this->attach($product, 'all_women-tops', '014001999');
        $this->attach($product, 'all_women-tops-tshirt', '014001999');
        $this->attach($product, 'all_women-inner', '008004001008004009');
        $this->attach($product, 'all_women-inner-heattech', '008004001008004009');

        $this->assertSame('all_women-inner-heattech', $this->service->getPrimaryCategory($product)->code);
    }

    /**
     * 性別欄位是空的（真實資料裡有這種），就不套性別條件、照 sort 選，
     * 不要因為對不到而整個回 null。
     */
    public function test_a_product_without_a_gender_still_gets_a_primary_category(): void
    {
        $product = $this->createProduct(['gender' => '']);
        $this->attach($product, 'feature', '002001035');
        $this->attach($product, 'feature-new', '002001035');
        $this->attach($product, 'feature-new-women', '002001035');

        $this->assertSame('feature-new-women', $this->service->getPrimaryCategory($product)->code);
    }

    /**
     * 商品頁的分類連結只到品項層為止，錨點層（levelThree）不是使用者會想
     * 點進去逛的分類，不該出現在連結列表裡。
     */
    public function test_product_page_category_links_exclude_level_three(): void
    {
        $this->createCategory('all_women-tops-anchor', '女裝/男女適穿', 'all_women-tops', CategoryLevel::Three);

        $product = $this->createProduct(['gender' => '女裝']);
        $this->attach($product, 'all_women-tops', '014001999');
        $this->attach($product, 'all_women-tops-tshirt', '014001999');
        $this->attach($product, 'all_women-tops-anchor', '014001999');

        $links = $this->service->getCategoryLinksForProductPage($product);

        // orderBy('level', 'desc') 先列品項層（T恤），再列大類（上衣類）
        $this->assertSame(['T恤', '上衣類'], $links->pluck('name')->values()->all());
    }

    /**
     * 男女適穿的商品在男裝、女裝樹下各掛一份同名分類，畫面上只留一個，
     * 不然會看到兩個一模一樣的連結。
     */
    public function test_product_page_category_links_deduplicate_by_name(): void
    {
        $this->createCategory('all_men', '男裝', null, CategoryLevel::Top);
        $this->createCategory('all_men-tops', '上衣類', 'all_men', CategoryLevel::One);

        $product = $this->createProduct(['gender' => '男女適穿']);
        $this->attach($product, 'all_women-tops', '014001999');
        $this->attach($product, 'all_men-tops', '014001999');

        $links = $this->service->getCategoryLinksForProductPage($product);

        $this->assertCount(1, $links);
    }

    /**
     * 再多分類連結就從導覽變成雜訊，上限是 5 個。
     */
    public function test_product_page_category_links_are_capped_at_five(): void
    {
        $product = $this->createProduct(['gender' => '女裝']);
        $this->attach($product, 'all_women-tops', '014001999');
        $this->attach($product, 'all_women-tops-tshirt', '014001999');

        for ($i = 1; $i <= 5; $i++) {
            $this->createCategory("all_women-extra{$i}", "分類{$i}", 'all_women', CategoryLevel::One);
            $this->attach($product, "all_women-extra{$i}", '014001999');
        }

        $links = $this->service->getCategoryLinksForProductPage($product);

        $this->assertCount(5, $links);
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
            'code' => "46000{$sequence}",
            'product_code' => "u46000{$sequence}",
            'sex' => '女裝',
            'gender' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
        ], $attributes)));
    }

    private function attach(HmallProduct $product, string $categoryCode, string $sort): void
    {
        $category = HmallCategory::where('brand', $product->brand)
            ->where('code', $categoryCode)
            ->firstOrFail();

        $product->categories()->attach($category->id, ['sort' => $sort]);
    }
}
