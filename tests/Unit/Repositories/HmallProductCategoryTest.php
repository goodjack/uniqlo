<?php

namespace Tests\Unit\Repositories;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallPriceHistory;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use stdClass;
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
     * 寫不進去的商品要回報是「哪幾件」，不能只回報幾件。
     *
     * 缺貨判定要靠這份編號清單把它們排除掉：那幾件在來源其實還在，只是資料沒寫進去，
     * 沒排除就會被當成「今天沒看到」而標成下架。
     */
    public function test_reports_which_products_failed_to_save(): void
    {
        $products = $this->products();

        // 價格欄位是 decimal，塞進不是數字的值會被 MySQL 擋下來（strict mode）
        $products[0]->minPrice = '這不是價格';

        $result = $this->repository->saveProductsFromV3($products);

        $this->assertSame(['u0000000053204'], $result->failedProductCodes);
        $this->assertSame(0, $result->unidentifiedFailureCount);
        // 同一頁其他商品照樣要寫進去，一筆壞資料不該拖垮整頁
        $this->assertSame(count($products) - 1, HmallProduct::count());
    }

    public function test_reports_an_empty_list_when_every_product_saves(): void
    {
        $result = $this->repository->saveProductsFromV3($this->products());

        $this->assertSame([], $result->failedProductCodes);
        $this->assertSame(0, $result->unidentifiedFailureCount);
        $this->assertFalse($result->hasFailures());
    }

    /**
     * 拿不到商品編號的失敗要單獨算一個數字，不能安靜吞掉。
     *
     * 這種失敗沒辦法從缺貨判定裡排除（不知道要排除誰），呼叫端只能整輪不做缺貨判定，
     * 所以它必須看得到這個數字。官方回傳格式跑掉時 productCode 就可能是空的。
     */
    public function test_a_failure_without_a_product_code_is_counted_separately(): void
    {
        $products = $this->products();

        $products[0]->productCode = '';
        $products[0]->minPrice = '這不是價格';

        $result = $this->repository->saveProductsFromV3($products);

        $this->assertSame([], $result->failedProductCodes);
        $this->assertSame(1, $result->unidentifiedFailureCount);
        $this->assertTrue($result->hasFailures());
    }

    /**
     * save、syncCategories、寫價格歷史三步要在同一個交易裡：分類同步半路丟例外時，
     * 不能讓新價格已經留在資料庫裡——不然下次抓到同一個價格會被判「沒變」直接跳過，
     * 價格走勢就永久缺一筆。
     *
     * categorySortList 塞一筆 code 是陣列的項目：syncCategories 拿它當 mapWithKeys
     * 的 array key 時，PHP 對非 int/string 的 key 一律丟 TypeError，這是會在正式環境
     * 真的發生的例外（官方回傳格式跑掉），不是為了測試硬造的假輸入。
     */
    public function test_a_failed_category_sync_rolls_back_the_saved_price_and_history(): void
    {
        // 先正常存一次，讓商品在資料庫裡有一筆基準價格，才能比對「有沒有被改到」
        $this->repository->saveProductsFromV3($this->products());

        $product = HmallProduct::where('product_code', 'u0000000053204')->firstOrFail();
        $originalMinPrice = $product->min_price;
        $historyCountBeforeRetry = HmallPriceHistory::count();

        $products = $this->products();
        // 換一個不同的價格，確保這次會被判「價格有變」而嘗試寫歷史
        $products[0]->minPrice = $originalMinPrice + 100;

        $badSort = new stdClass;
        $badSort->code = ['this-is-not-a-string'];
        $badSort->sort = '000000001';
        $products[0]->categorySortList[] = $badSort;

        $result = $this->repository->saveProductsFromV3($products);

        $this->assertSame(['u0000000053204'], $result->failedProductCodes);
        $this->assertSame($originalMinPrice, $product->fresh()->min_price);
        $this->assertSame($historyCountBeforeRetry, HmallPriceHistory::count());
    }

    /**
     * 分類主檔整批寫不進去時，不可以把例外往外丟。
     *
     * saveCategoriesFromV3() 在逐商品的 try/catch 之外，它丟的例外會穿出呼叫端的
     * retry，而 retry 對所有非 403 的例外都當成可重試——等於拿資料庫的問題去重打
     * 官網好幾次，最後還被歸成「整頁沒抓到」，通知寫「目錄有缺頁」，把人指向錯的
     * 方向。實際上 HTTP 全部成功、目錄也完整看到了。
     *
     * 正確的歸類是「這一頁的商品都寫入失敗」：缺貨判定把它們排除掉就不會冤枉標成
     * 下架，通知也寫得出真正的原因。
     */
    public function test_a_category_master_failure_is_reported_as_write_failures(): void
    {
        $products = $this->products();
        // 分類的 code 欄位是 string(255)，超長值會讓整批 upsert 在 strict mode 下丟例外
        $products[0]->topCategories[0]->code = str_repeat('x', 300);

        $result = $this->repository->saveProductsFromV3($products);

        // 例外沒有往外丟，而是整頁歸成寫入失敗
        $this->assertSame(
            ['u0000000053204', 'u0000000053340'],
            $result->failedProductCodes
        );
        $this->assertSame(0, $result->unidentifiedFailureCount);
        // 沒有對照表就不寫商品，免得分類關聯整頁掛不上去
        $this->assertSame(0, HmallProduct::count());
    }

    /**
     * 真實的官方回傳樣本（by-description 的兩筆商品）。
     */
    private function products(): array
    {
        $json = file_get_contents(base_path('tests/stubs/hmall-search-v3-response.json'));

        return json_decode($json)->resp[0]->productList;
    }
}
