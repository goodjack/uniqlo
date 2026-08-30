<?php

namespace Tests\Unit\Enums;

use App\Enums\ProductTag;
use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTagTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 同一個標籤在清單頁走記憶體過濾、在分類頁走 SQL，兩邊必須給出同一批商品。
     * 這個測試存在的目的就是擋住那兩份實作各自演化。
     *
     * @dataProvider tagProvider
     */
    public function test_sql_and_in_memory_filtering_agree(ProductTag $tag): void
    {
        $this->seedProductsCoveringEveryTag();

        $viaSql = HmallProduct::query()
            ->where(fn ($group) => $tag->applyTo($group))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $viaMemory = HmallProduct::all()
            ->filter(fn (HmallProduct $product) => $tag->matches($product))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($viaMemory, $viaSql, "{$tag->value} 的兩種篩選方式結果不同");
    }

    public static function tagProvider(): array
    {
        return collect(ProductTag::cases())
            ->mapWithKeys(fn (ProductTag $tag) => [$tag->value => [$tag]])
            ->all();
    }

    /**
     * identity 的代碼要連引號一起比對，否則 APP 會命中以它開頭的其他代碼。
     */
    public function test_identity_codes_are_matched_exactly(): void
    {
        $this->createProduct(['identity' => '["APPAREL"]']);

        $this->assertSame(0, HmallProduct::query()
            ->where(fn ($group) => ProductTag::LimitedOffer->applyTo($group))
            ->count());
    }

    private function seedProductsCoveringEveryTag(): void
    {
        foreach ([
            '["time_doptimal"]', '["APP"]', '["ECONLY"]', '["concessional_rate"]',
            '["new_product"]', '["COMING SOON"]', '["COMING"]', '["multi_buy"]',
            '["SET"]', '["ONLINE SPECIAL"]', '["APPAREL"]',
        ] as $identity) {
            $this->createProduct(['identity' => $identity]);
        }

        // 現價就是史上最低，而且確實變動過
        $this->createProduct([
            'identity' => '[]',
            'min_price' => '790.00',
            'lowest_record_price' => '790.00',
            'highest_record_price' => '990.00',
        ]);

        // 從沒變過價，不算史上最低
        $this->createProduct([
            'identity' => '[]',
            'min_price' => '790.00',
            'lowest_record_price' => '790.00',
            'highest_record_price' => '790.00',
        ]);
    }

    private function createProduct(array $attributes): HmallProduct
    {
        static $sequence = 0;
        $sequence++;

        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => "測試商品 {$sequence}",
            'code' => "4500{$sequence}",
            'product_code' => "u4500{$sequence}",
            'stock' => 'Y',
            'min_price' => '990.00',
            'lowest_record_price' => '990.00',
            'highest_record_price' => '990.00',
        ], $attributes)));
    }
}
