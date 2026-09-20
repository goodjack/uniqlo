<?php

namespace Tests\Unit\Services;

use App\Enums\CrawlOutcome;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\HmallProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Tests\TestCase;

class HmallProductServiceDbTest extends TestCase
{
    use RefreshDatabase;

    private HmallProductService $service;

    private HmallProductRepository $mockHmallRepository;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);

        Config::set('uniqlo.api.v3.description.tw', 'https://api.example.com/description/');

        $this->mockHmallRepository = $this->createMock(HmallProductRepository::class);
        $this->mockHmallRepository->method('updateProductDescriptionsFromV3')->willReturn(true);

        $mockProductRepository = $this->createMock(ProductRepository::class);

        $this->service = new HmallProductService($this->mockHmallRepository, $mockProductRepository);
    }

    public function test_detail_counter_only_incremented_on_success()
    {
        // Insert 2 HmallProducts: PCODE001 (will succeed), PCODE002 (will fail)
        HmallProduct::unguarded(function () {
            HmallProduct::create(['product_code' => 'PCODE001', 'brand' => 'UNIQLO']);
            HmallProduct::create(['product_code' => 'PCODE002', 'brand' => 'UNIQLO']);
        });

        Http::fake([
            // PCODE001 description endpoints → 200 success
            'https://api.example.com/description/PCODE001/*' => Http::response('<html>instruction</html>', 200),
            // PCODE002 description endpoints → 500 failure (retry exhausted)
            'https://api.example.com/description/PCODE002/*' => Http::response([], 500),
        ]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProductDescriptions('UNIQLO');

        // detailCounter should be 1: PCODE001 succeeded, PCODE002 failed and was skipped
        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('detailCounter');
        $property->setAccessible(true);
        $detailCounter = $property->getValue($this->service);

        $this->assertEquals(1, $detailCounter, 'Failed items must not increment detailCounter');
    }

    /**
     * 真的跑一次完整掃描，直接看資料庫裡三件既有商品最後的下場。
     *
     * A：來源還有，但這一輪寫不進去 → 不可以被標成下架（這次修正的重點）
     * B：來源已經沒有了 → 要被標成下架
     * C：來源還有而且寫入成功 → 維持在售
     *
     * 只驗「缺貨判定有沒有被呼叫」不夠：真正會出事的是那幾筆資料列的 stockout_at。
     */
    public function test_a_product_that_failed_to_save_is_not_marked_as_stocked_out(): void
    {
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');
        Config::set('app.crawler.page_sizes.hmall_products', 24);
        Config::set('app.crawler.delay.min', 0);
        Config::set('app.crawler.delay.max', 0);

        $products = $this->searchResponseProducts();
        // A 在來源還在，但價格欄位是 decimal，塞進不是數字的值會被 MySQL 擋下來
        $products[0]->minPrice = '這不是價格';

        $codeOfFailedProduct = $products[0]->productCode;
        $codeOfSavedProduct = $products[1]->productCode;
        $codeOfVanishedProduct = 'u0000000000000';

        $this->seedProductLastUpdatedYesterday($codeOfFailedProduct);
        $this->seedProductLastUpdatedYesterday($codeOfVanishedProduct);
        $this->seedProductLastUpdatedYesterday($codeOfSavedProduct);

        Http::fake([
            'https://api.example.com/search' => Http::response([
                'resp' => [
                    [
                        'productList' => $products,
                        'productSum' => count($products),
                    ],
                ],
            ]),
        ]);

        $service = new HmallProductService(
            app(HmallProductRepository::class),
            app(ProductRepository::class)
        );

        $result = $service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertNull(
            $this->stockoutAtOf($codeOfFailedProduct),
            '寫入失敗的商品在來源其實還在，不可以被標成下架'
        );
        $this->assertNotNull(
            $this->stockoutAtOf($codeOfVanishedProduct),
            '來源已經沒有的商品還是要被標成下架，缺貨判定不能整輪停掉'
        );
        $this->assertNull(
            $this->stockoutAtOf($codeOfSavedProduct),
            '正常寫入的商品要維持在售'
        );
    }

    /**
     * hmall_products.product_code 允許 NULL；排除清單非空時，NULL 不能被
     * whereNotIn 誤保護住，否則同品牌所有編號空白的舊資料都不會被標下架
     * ——因為 SQL 的 NULL NOT IN (...) 永遠不成立。
     */
    public function test_a_product_with_null_product_code_is_still_marked_as_stocked_out_when_exclusions_exist(): void
    {
        $codeOfFailedProduct = 'u0000000099999';

        $this->seedProductLastUpdatedYesterday($codeOfFailedProduct);
        $idOfNullCodeProduct = $this->seedNullCodeProductLastUpdatedYesterday();

        $repository = app(HmallProductRepository::class);

        $repository->setStockoutHmallProducts('UNIQLO', null, [$codeOfFailedProduct]);

        $this->assertNull(
            $this->stockoutAtOf($codeOfFailedProduct),
            '具名的寫入失敗商品在排除清單內，不可以被標成下架'
        );
        $this->assertNotNull(
            HmallProduct::find($idOfNullCodeProduct)->stockout_at,
            'product_code 是 NULL 的舊商品不在排除清單裡，不能因為 NULL NOT IN (...) 不成立而被誤保護，要照樣標下架'
        );
    }

    /**
     * 建一件 product_code 是 NULL、上次更新是昨天的既有商品。
     */
    private function seedNullCodeProductLastUpdatedYesterday(): int
    {
        $product = HmallProduct::unguarded(function () {
            return HmallProduct::create(['product_code' => null, 'brand' => 'UNIQLO']);
        });

        DB::table('hmall_products')
            ->where('id', $product->id)
            ->update([
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ]);

        return $product->id;
    }

    /**
     * 建一件「上次更新是昨天」的既有商品。
     *
     * 缺貨判定比的是 updated_at，所以時間要落在今天之前；Eloquent 存檔會自動蓋掉
     * updated_at，只能用 query builder 直接寫。
     */
    private function seedProductLastUpdatedYesterday(string $productCode): void
    {
        HmallProduct::unguarded(function () use ($productCode) {
            HmallProduct::create(['product_code' => $productCode, 'brand' => 'UNIQLO']);
        });

        DB::table('hmall_products')
            ->where('product_code', $productCode)
            ->update([
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ]);
    }

    private function stockoutAtOf(string $productCode)
    {
        return HmallProduct::where('product_code', $productCode)->value('stockout_at');
    }

    /**
     * 真實的官方回傳樣本（兩筆商品）。
     */
    private function searchResponseProducts(): array
    {
        $json = file_get_contents(base_path('tests/stubs/hmall-search-v3-response.json'));

        return json_decode($json)->resp[0]->productList;
    }
}
