<?php

namespace Tests\Unit\Repositories;

use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use stdClass;
use Tests\TestCase;

class HmallProductStockoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 官網回的資料跟資料庫一字不差時 save() 不會寫入；這輪看到的商品仍要算
     * 「還在」，不能被缺貨判定掃到。
     */
    public function test_a_product_seen_again_with_identical_data_is_not_marked_as_stocked_out(): void
    {
        $repository = app(HmallProductRepository::class);

        Carbon::setTestNow('2026-10-08 09:05:00');
        $repository->saveProductsFromV3([$this->unchangedProduct()]);

        Carbon::setTestNow('2026-10-09 09:05:00');
        $repository->saveProductsFromV3([$this->unchangedProduct()]);
        $repository->setStockoutHmallProducts('UNIQLO');

        $product = HmallProduct::where('product_code', 'u0000000053204')->firstOrFail();
        $this->assertTrue($product->updated_at->isSameDay(now()));
        $this->assertNull($product->stockout_at);
    }

    /**
     * 價格用資料庫 decimal 欄位回傳的格式，其餘欄位留空，重存時沒有任何欄位會變。
     */
    private function unchangedProduct(): stdClass
    {
        $product = new stdClass;
        $product->productCode = 'u0000000053204';
        $product->minPrice = '790.00';
        $product->maxPrice = '790.00';
        $product->stock = 'Y';

        return $product;
    }
}
