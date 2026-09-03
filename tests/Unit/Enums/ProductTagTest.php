<?php

namespace Tests\Unit\Enums;

use App\Enums\ProductTag;
use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductTagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 檔期判斷比的是「現在」。時間釘住，SQL 與記憶體兩邊才是同一個時刻，
        // 測試也不會在檔期邊界上隨機失敗。
        Carbon::setTestNow('2026-09-03 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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
     * identity 的代碼要精準比對，不能命中以它開頭的其他代碼。
     * ECONLYAD 是真實資料上的反例：本機有 14 件，它們不是網路獨家。
     */
    public function test_identity_codes_are_matched_exactly(): void
    {
        $this->createProduct(['identity' => '["ECONLYAD"]']);

        $this->assertSame(0, HmallProduct::query()
            ->where(fn ($group) => ProductTag::OnlineSpecial->applyTo($group))
            ->count());
    }

    /**
     * 期間限定的判準要跟限時特價清單頁一致：落在檔期內就算，不必等官方掛
     * time_doptimal。原本兩邊不是包含關係——真實資料上頁面 65 件、標籤 148 件。
     */
    public function test_limited_offer_follows_the_same_rule_as_its_list_page(): void
    {
        $inPeriod = $this->createProduct([
            'identity' => '[]',
            'time_limited_begin' => '2026-09-01 00:00:00',
            'time_limited_end' => '2026-09-30 23:59:59',
        ]);

        $this->assertTrue(ProductTag::LimitedOffer->matches($inPeriod));
    }

    /**
     * 檔期外、或只填了一端的商品都不算。只有開始沒有結束不構成一段檔期，
     * SQL 的 NULL 比較也不成立，兩邊要給同一個答案。
     */
    public function test_limited_offer_ignores_expired_future_and_half_filled_periods(): void
    {
        $expired = $this->createProduct([
            'identity' => '[]',
            'time_limited_begin' => '2026-08-01 00:00:00',
            'time_limited_end' => '2026-08-31 23:59:59',
        ]);

        $future = $this->createProduct([
            'identity' => '[]',
            'time_limited_begin' => '2026-10-01 00:00:00',
            'time_limited_end' => '2026-10-31 23:59:59',
        ]);

        $beginOnly = $this->createProduct([
            'identity' => '[]',
            'time_limited_begin' => '2026-09-01 00:00:00',
        ]);

        $endOnly = $this->createProduct([
            'identity' => '[]',
            'time_limited_end' => '2026-09-30 23:59:59',
        ]);

        foreach ([$expired, $future, $beginOnly, $endOnly] as $product) {
            $this->assertFalse(ProductTag::LimitedOffer->matches($product));
        }
    }

    /**
     * APP 與 ECONLY 從期間限定拿掉：它們講的是「哪裡買得到」，不是檔期。
     * ECONLY 屬於網路獨家那一半，保留。
     */
    public function test_app_and_ec_only_are_no_longer_limited_offers(): void
    {
        $app = $this->createProduct(['identity' => '["APP"]']);
        $ecOnly = $this->createProduct(['identity' => '["ECONLY"]']);

        $this->assertFalse(ProductTag::LimitedOffer->matches($app));
        $this->assertFalse(ProductTag::LimitedOffer->matches($ecOnly));
        $this->assertTrue(ProductTag::OnlineSpecial->matches($ecOnly));
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

        // 檔期的四種狀態都要進來，一致性測試才蓋得到 LimitedOffer 的日期分支
        $this->createProduct([
            'identity' => '[]',
            'time_limited_begin' => '2026-09-01 00:00:00',
            'time_limited_end' => '2026-09-30 23:59:59',
        ]);
        $this->createProduct([
            'identity' => '[]',
            'time_limited_begin' => '2026-08-01 00:00:00',
            'time_limited_end' => '2026-08-31 23:59:59',
        ]);
        $this->createProduct([
            'identity' => '[]',
            'time_limited_begin' => '2026-10-01 00:00:00',
            'time_limited_end' => '2026-10-31 23:59:59',
        ]);
        // 只有一端的檔期：SQL 的 NULL 比較不成立，記憶體版也必須是 false
        $this->createProduct([
            'identity' => '[]',
            'time_limited_begin' => '2026-09-01 00:00:00',
        ]);
        $this->createProduct([
            'identity' => '[]',
            'time_limited_end' => '2026-09-30 23:59:59',
        ]);

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
