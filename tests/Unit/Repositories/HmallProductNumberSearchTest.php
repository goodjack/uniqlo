<?php

namespace Tests\Unit\Repositories;

use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HmallProductNumberSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 數字搜尋的查詢次數不可以跟著卡片數長。
     *
     * 卡片會讀 japanProduct 判斷要不要顯示影片圖示，所以這條查詢沒有先 eager load
     * 的話，就是一張卡片補一次查詢。舊行為是 code 精準比對、通常只有一筆，所以
     * 看不出來；改成共用號碼比對之後一次可能撈出幾十筆，這才變成實際成本。
     */
    public function test_a_number_search_does_not_query_japan_products_once_per_card(): void
    {
        $this->createProductsSharingTheNumber('474238', 3);

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        $products = app(HmallProductRepository::class)->findHmallProductsByCodeOrSharedNumber('474238');
        $products->each(fn (HmallProduct $product) => $product->japanProduct);

        $this->assertCount(3, $products);
        // 主查詢一條加 eager load 一條，跟卡片數無關
        $this->assertCount(2, $statements);
    }

    /**
     * 卡片用不到的長文字欄不要一起撈出來。
     */
    public function test_a_number_search_does_not_select_every_column(): void
    {
        $this->createProductsSharingTheNumber('474238', 1);

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        app(HmallProductRepository::class)->findHmallProductsByCodeOrSharedNumber('474238');

        $this->assertStringNotContainsString('select *', $statements[0]);
        $this->assertStringNotContainsString('instruction', $statements[0]);
    }

    private function createProductsSharingTheNumber(string $number, int $count): void
    {
        foreach (range(1, $count) as $index) {
            HmallProduct::unguarded(fn () => HmallProduct::create([
                'brand' => 'UNIQLO',
                'code' => (string) (450000 + $index),
                'product_code' => sprintf('u%011d', $index),
                'name' => "測試商品 {$number} / 48251{$index}",
                'identity' => '[]',
                'stock' => 'Y',
                'min_price' => '990.00',
            ]));
        }
    }
}
