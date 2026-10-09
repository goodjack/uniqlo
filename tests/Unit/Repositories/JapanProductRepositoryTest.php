<?php

namespace Tests\Unit\Repositories;

use App\Models\JapanProduct;
use App\Repositories\JapanProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JapanProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_listed_again_is_no_longer_marked_as_stocked_out(): void
    {
        $repository = app(JapanProductRepository::class);
        $repository->saveProducts([$this->item()]);
        JapanProduct::where('l1Id', '483870')->update(['stockout_at' => now()->subDays(3)]);

        $repository->saveProducts([$this->item()]);

        $this->assertNull(JapanProduct::where('l1Id', '483870')->firstOrFail()->stockout_at);
    }

    /**
     * 官網回的資料跟資料庫一字不差時 save() 不會寫入；這輪看到的商品仍要算
     * 「還在」，不能被下架判定掃到。
     */
    public function test_a_product_seen_again_with_identical_data_is_not_marked_as_stocked_out(): void
    {
        $repository = app(JapanProductRepository::class);

        Carbon::setTestNow('2026-10-08 09:30:00');
        $repository->saveProducts([$this->item()]);

        Carbon::setTestNow('2026-10-09 09:30:00');
        $repository->saveProducts([$this->item()]);
        $repository->setStockoutProducts();

        $product = JapanProduct::where('l1Id', '483870')->firstOrFail();
        $this->assertTrue($product->updated_at->isSameDay(now()));
        $this->assertNull($product->stockout_at);
    }

    /**
     * 官網改格式（例如少了 rating）時每件都寫不進去。寫不進去的要回報出來，
     * 下架判定排除它們，否則整個品牌會因為 updated_at 沒更新而被標成下架。
     */
    public function test_a_product_that_fails_to_save_is_reported_and_kept_out_of_stockout(): void
    {
        $repository = app(JapanProductRepository::class);

        Carbon::setTestNow('2026-10-08 09:30:00');
        $repository->saveProducts([$this->item()]);

        Carbon::setTestNow('2026-10-09 09:30:00');
        $broken = $this->item();
        unset($broken->rating);

        $result = $repository->saveProducts([$broken]);
        $repository->setStockoutProducts('UNIQLO', null, $result->failedProductCodes);

        $this->assertSame(['483870'], $result->failedProductCodes);
        $this->assertSame(0, $result->unidentifiedFailureCount);
        $this->assertNull(JapanProduct::where('l1Id', '483870')->firstOrFail()->stockout_at);
    }

    public function test_a_failure_without_an_l1_id_is_counted_as_unidentified(): void
    {
        $broken = $this->item();
        unset($broken->l1Id);

        $result = app(JapanProductRepository::class)->saveProducts([$broken]);

        $this->assertSame([], $result->failedProductCodes);
        $this->assertSame(1, $result->unidentifiedFailureCount);
    }

    /**
     * 欄位格式跟資料庫回傳的一致（評分 4.5000），重存時沒有任何欄位會變。
     */
    private function item(): object
    {
        return json_decode(json_encode([
            'l1Id' => '483870',
            'productId' => 'E483870-000',
            'name' => '牛津襯衫',
            'genderCategory' => 'WOMEN',
            'rating' => ['average' => '4.5000', 'count' => 12],
            'priceGroup' => '00',
            'prices' => ['base' => ['value' => 2990]],
            'images' => ['main' => [], 'sub' => []],
        ]));
    }
}
