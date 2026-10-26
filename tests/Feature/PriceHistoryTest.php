<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    // 事後補抓的舊日期紀錄 id 會比較大，走勢圖與「最後一筆＝現況」都要照日期排，不能靠寫入順序
    public function test_price_histories_are_ordered_by_date_even_when_backfilled_later(): void
    {
        $product = $this->seedProduct();
        $this->seedHistories($product, [
            1 => ['2026-10-12 08:00:00', 990],
            2 => ['2026-10-17 08:00:00', 590],
            4 => ['2026-10-13 08:00:00', 790],
            3 => ['2026-10-13 08:00:00', 890],
        ]);

        $histories = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->viewData('hmallPriceHistories');

        $this->assertSame([1, 3, 4, 2], $histories->pluck('id')->all());
    }

    // 排序是這張表的規則，不只商品頁：直接讀關聯也要照日期排
    public function test_the_relation_itself_orders_price_histories_by_date(): void
    {
        $product = $this->seedProduct();
        $this->seedHistories($product, [
            1 => ['2026-10-12 08:00:00', 990],
            2 => ['2026-10-17 08:00:00', 590],
            3 => ['2026-10-13 08:00:00', 890],
        ]);

        $this->assertSame([1, 3, 2], $product->hmallPriceHistories->pluck('id')->all());
    }

    private function seedProduct(): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => 'UNIQLO',
            'name' => '短袖上衣',
            'product_name' => '短袖上衣',
            'code' => '990001',
            'product_code' => 'u990001',
            'sex' => '女裝',
            'gender' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 590,
            'lowest_record_price' => 590,
            'highest_record_price' => 990,
        ]));
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $histories  id => [建立時間, 最低價]
     */
    private function seedHistories(HmallProduct $product, array $histories): void
    {
        foreach ($histories as $id => [$createdAt, $minPrice]) {
            DB::table('hmall_price_histories')->insert([
                'id' => $id,
                'hmall_product_id' => $product->id,
                'min_price' => $minPrice,
                'max_price' => $minPrice,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
