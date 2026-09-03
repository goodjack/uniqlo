<?php

namespace Tests\Unit\Repositories;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HmallProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    private HmallProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(HmallProductRepository::class);
    }

    public function test_saves_category_master_data_from_product_response(): void
    {
        $this->repository->saveProductsFromV3($this->products());

        // 樣本裡兩件商品合計掛到的不重複分類
        $this->assertSame(32, HmallCategory::count());

        $top = $this->findCategory('ALL');
        $this->assertSame('全商品', $top->name);
        $this->assertSame(CategoryLevel::Top, $top->level);
        $this->assertNull($top->parent_code);
    }

    public function test_category_level_matches_the_array_it_came_from(): void
    {
        $this->repository->saveProductsFromV3($this->products());

        $this->assertSame(CategoryLevel::Top, $this->findCategory('all_women')->level);
        $this->assertSame(CategoryLevel::One, $this->findCategory('all_women-bottoms')->level);
        $this->assertSame(CategoryLevel::Two, $this->findCategory('all_women-bottoms-widepants')->level);
        $this->assertSame(CategoryLevel::Three, $this->findCategory('all_women-bottoms-widepants-anchor09')->level);
    }

    public function test_every_category_can_be_traced_back_to_a_root(): void
    {
        $this->repository->saveProductsFromV3($this->products());

        foreach (HmallCategory::all() as $category) {
            $this->assertNotNull(
                $this->traceToRoot($category),
                "分類 {$category->code} 無法回溯到根節點"
            );
        }
    }

    /**
     * 分類的身分是品牌加 code。兩家目前只有頂層的 ALL 同名，但沒有保證未來不會撞，
     * 撞到時不能讓後爬到的那家覆蓋先爬到的。
     */
    public function test_the_same_category_code_in_both_brands_are_separate_rows(): void
    {
        $this->repository->saveProductsFromV3($this->products(), 'UNIQLO');
        $this->repository->saveProductsFromV3($this->products(), 'GU');

        $this->assertSame(2, HmallCategory::where('code', 'ALL')->count());
        $this->assertSame(
            ['GU', 'UNIQLO'],
            HmallCategory::where('code', 'ALL')->pluck('brand')->map->value->sort()->values()->all()
        );
    }

    public function test_links_product_to_its_categories_with_official_sort(): void
    {
        $this->repository->saveProductsFromV3($this->products());

        $product = HmallProduct::where('product_code', 'u0000000053204')->firstOrFail();

        $this->assertCount(24, $product->categories);

        $anchor = $product->categories->firstWhere('code', 'all_women-bottoms-widepants-anchor09');
        $this->assertSame('006002009', $anchor->pivot->sort);
    }

    public function test_converts_new_epoch_milliseconds_to_new_at(): void
    {
        $this->repository->saveProductsFromV3($this->products());

        $product = HmallProduct::where('product_code', 'u0000000053204')->firstOrFail();

        $this->assertSame('1767573000000', $product->new);
        $this->assertSame('2026-01-05 08:30:00', $product->new_at->toDateTimeString());
    }

    public function test_resync_drops_categories_the_product_no_longer_belongs_to(): void
    {
        $this->repository->saveProductsFromV3($this->products());

        $product = HmallProduct::where('product_code', 'u0000000053204')->firstOrFail();
        $this->assertCount(24, $product->categories);

        // 第二次回傳只剩兩個分類，模擬官方把商品移出其他分類
        $shrunk = $this->products();
        $shrunk[0]->topCategories = [$shrunk[0]->topCategories[0]];
        $shrunk[0]->levelOne = [];
        $shrunk[0]->levelTwo = [];
        $shrunk[0]->levelThree = [];

        $this->repository->saveProductsFromV3($shrunk);

        $this->assertCount(1, $product->fresh()->categories);
    }

    /**
     * 分類回傳缺漏時不要把商品的既有分類全部清掉。
     */
    public function test_empty_category_response_leaves_existing_links_untouched(): void
    {
        $this->repository->saveProductsFromV3($this->products());

        $stripped = $this->products();
        foreach (['topCategories', 'levelOne', 'levelTwo', 'levelThree'] as $level) {
            $stripped[0]->$level = [];
        }

        $this->repository->saveProductsFromV3($stripped);

        $product = HmallProduct::where('product_code', 'u0000000053204')->firstOrFail();
        $this->assertCount(24, $product->categories);
    }

    private function findCategory(string $code): ?HmallCategory
    {
        // 分類的身分是品牌加 code，樣本全部是 UNIQLO 的
        return HmallCategory::where('brand', 'UNIQLO')->where('code', $code)->first();
    }

    private function traceToRoot(HmallCategory $category): ?HmallCategory
    {
        $current = $category;
        $depth = 0;

        while ($current->parent_code !== null) {
            $current = $this->findCategory($current->parent_code);

            if ($current === null || ++$depth > 10) {
                return null;
            }
        }

        return $current;
    }

    /**
     * 真實的官方回傳樣本（by-description 的兩筆商品）。
     */
    /**
     * 寫不進去的商品要被數出來回報，不能只寫 log 就當這一頁沒事。
     */
    public function test_reports_how_many_products_failed_to_save(): void
    {
        $products = $this->products();

        // 價格欄位是 decimal，塞進不是數字的值會被 MySQL 擋下來（strict mode）
        $products[0]->minPrice = '這不是價格';

        $failedCount = $this->repository->saveProductsFromV3($products);

        $this->assertSame(1, $failedCount);
        // 同一頁其他商品照樣要寫進去，一筆壞資料不該拖垮整頁
        $this->assertSame(count($products) - 1, HmallProduct::count());
    }

    public function test_reports_zero_when_every_product_saves(): void
    {
        $this->assertSame(0, $this->repository->saveProductsFromV3($this->products()));
    }

    private function products(): array
    {
        $json = file_get_contents(base_path('tests/stubs/hmall-search-v3-response.json'));

        return json_decode($json)->resp[0]->productList;
    }
}
