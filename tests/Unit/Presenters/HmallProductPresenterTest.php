<?php

namespace Tests\Unit\Presenters;

use App\Models\HmallProduct;
use App\Presenters\HmallProductPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * HmallProductPresenter::isOnOffer() 決定收藏頁「只看優惠中」篩選鈕收不收
 * 這件商品。判準來自 Jack 2026-09-14 的定案：期間限定特價、網路限定特價、
 * 歷史新低價、合購商品、特價商品、APP 限定特價算優惠中；新款商品、即將
 * 上市、網路獨家販售、穿搭與瀏覽排行榜不算；已售罄一律不算，就算同時掛著
 * 優惠標籤也一樣。
 */
class HmallProductPresenterTest extends TestCase
{
    use RefreshDatabase;

    private HmallProductPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presenter = new HmallProductPresenter;
    }

    /**
     * @dataProvider offerAttributesProvider
     */
    public function test_products_with_an_offer_attribute_are_on_offer(array $attributes): void
    {
        $product = $this->createProduct($attributes);

        $this->assertTrue($this->presenter->isOnOffer($product));
    }

    public static function offerAttributesProvider(): array
    {
        return [
            '期間限定特價' => [[
                'time_limited_begin' => now()->subDay(),
                'time_limited_end' => now()->addDay(),
            ]],
            '網路限定特價' => [['identity' => '["ECONLY"]']],
            '歷史新低價' => [[
                'min_price' => '500.00',
                'lowest_record_price' => '500.00',
                'highest_record_price' => '990.00',
                'lowest_record_price_count' => 1,
            ]],
            '合購商品' => [['identity' => '["multi_buy"]']],
            '特價商品' => [['identity' => '["concessional_rate"]']],
            'APP 限定特價' => [['identity' => '["APP"]']],
        ];
    }

    /**
     * @dataProvider nonOfferAttributesProvider
     */
    public function test_products_without_a_price_offer_are_not_on_offer(array $attributes): void
    {
        $product = $this->createProduct($attributes);

        $this->assertFalse($this->presenter->isOnOffer($product));
    }

    public static function nonOfferAttributesProvider(): array
    {
        return [
            '新款商品' => [['identity' => '["new_product"]']],
            '即將上市' => [['identity' => '["COMING SOON"]']],
            '網路獨家販售（不含 ECONLY）' => [['identity' => '["ONLINE SPECIAL"]']],
        ];
    }

    /**
     * 已售罄的商品即使同時掛著優惠標籤，也不算優惠中——現在買不到，優惠沒有
     * 可以採取的行動，對使用者來說跟「沒有優惠」是同一種結果。
     */
    public function test_a_stockout_product_is_never_on_offer_even_with_a_sale_tag(): void
    {
        $product = $this->createProduct([
            'identity' => '["concessional_rate"]',
            'stock' => 'N',
        ]);

        $this->assertFalse($this->presenter->isOnOffer($product));
    }

    /**
     * 穿搭、瀏覽排行榜是人氣排名，不是價格優惠，不該讓 isOnOffer 誤判成真。
     */
    public function test_a_popularity_rank_alone_does_not_count_as_an_offer(): void
    {
        $product = $this->createProduct(['identity' => '[]']);

        Cache::put('hmall_product:top_wearing_ranks', [$product->id => 1]);
        Cache::put('hmall_product:most_visited_ranks', [$product->id => 1]);

        // 先確認排名真的生效，排除「其實沒設成功、只是剛好也是 false」的偽陽性
        $this->assertSame(1, $product->top_wearing_rank);
        $this->assertSame(1, $product->most_visited_rank);
        $this->assertFalse($this->presenter->isOnOffer($product));
    }

    private function createProduct(array $attributes): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => '測試商品',
            'code' => '450001',
            'product_code' => 'u450001',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => '990.00',
            'lowest_record_price' => '990.00',
            'highest_record_price' => '990.00',
            'lowest_record_price_count' => 0,
        ], $attributes)));
    }
}
