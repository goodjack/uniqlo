<?php

namespace Tests\Unit\Presenters;

use App\Enums\ProductTag;
use App\Models\HmallProduct;
use App\Presenters\HmallProductPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

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
     * 兩家的代號都對到同一個標籤，卡片標籤連到清單頁；它講的是人氣，不算優惠。
     *
     * @dataProvider communityTopicProvider
     */
    public function test_both_brands_codes_map_to_the_community_topic_tag(string $brand, string $identity): void
    {
        $product = $this->createProduct(['brand' => $brand, 'identity' => json_encode([$identity])]);

        $this->assertSame(
            [ProductTag::CommunityTopic],
            collect(ProductTag::cases())->filter(fn (ProductTag $tag) => $tag->matches($product))->values()->all()
        );

        $tag = collect($this->presenter->getProductTags($product))->firstWhere('url', route('lists.community-topics'));

        $this->assertNotNull($tag, "{$brand} 的商品要有連到清單頁的標籤");
        $this->assertFalse($this->presenter->isOnOffer($product));
    }

    public static function communityTopicProvider(): array
    {
        return [
            'GU' => ['GU', 'HOT ITEM'],
            'UNIQLO' => ['UNIQLO', 'COMMUNITY_TOPICS'],
        ];
    }

    /**
     * 已售罄的商品即使掛著優惠標籤也不算：現在買不到。
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

        $this->assertSame(1, $product->top_wearing_rank);
        $this->assertSame(1, $product->most_visited_rank);
        $this->assertFalse($this->presenter->isOnOffer($product));
    }

    public function test_the_analytics_item_leaves_out_a_missing_price(): void
    {
        $product = $this->createProduct(['min_price' => null]);

        $this->assertArrayNotHasKey('price', $this->presenter->getAnalyticsItem($product));
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

    public function test_social_media_description_is_plain_text_without_double_escaping(): void
    {
        // 說明裡的 & 在商品頁是 &amp;，社群說明要還原成 &，輸出時才不會變成看得到的 &amp;
        $product = $this->createProduct(['instruction' => 'A&B', 'product_code' => 'u0001']);

        $this->assertStringStartsWith('A&B網路商店編號：u0001', $this->presenter->getSocialMediaDescription($product));
    }

    public static function lineBreakProvider(): array
    {
        return [
            '一般寫法' => ['A<br>B<br/>C<br />D', 'A<br>B<br>C<br>D'],
            '帶屬性' => ['A<br onmouseover="alert(1)">B', 'A<br>B'],
            '大寫' => ['A<BR style="x">B', 'A<br>B'],
            '結尾標籤' => ['A</br onclick="x">B', 'A<br>B'],
            '標籤名後面接換行字元' => ["A<br\nx onmouseover=alert(1)>B", 'A<br>B'],
            '其他標籤' => ['A<script>x</script>B', 'AxB'],
            '小於號後面接換行字元' => ["A<\nimg src=x onerror=alert(1)>B", 'A&lt;img src=x onerror=alert(1)&gt;B'],
            '刪掉※那行後拼出標籤' => ["A<\n※x<br>img src=x onerror=alert(1)>B", 'A&lt;img src=x onerror=alert(1)&gt;B'],
            '原本的實體不重複轉' => ['A&amp;B', 'A&amp;B'],
        ];
    }

    /**
     * 說明是原樣輸出的 HTML，換行標籤上的屬性要拿掉，不然官網資料被竄改時可以夾帶程式
     *
     * @dataProvider lineBreakProvider
     */
    public function test_description_keeps_only_bare_line_breaks(string $instruction, string $expected): void
    {
        $this->assertSame($expected, $this->presenter->getDescriptionBody($instruction));
    }

    public static function officialLabelProvider(): array
    {
        return [
            '標示在句子中間時整句拿掉' => [
                '- 寬鬆版型。<br><br>- 本產品使用再生材質。請參閱商品材質欄位的使用比例。<br>- 此頁面所展示的圖片顏色可能會包含未販售的商品顏色。',
                '- 寬鬆版型。<br><br>- 本產品使用再生材質。',
            ],
            '整行都是那一句時連行首一起拿掉' => ['寬鬆版型。<br>- 詳見商品材質欄位', '寬鬆版型。'],
            '標示自成一段時從標示切' => ['寬鬆版型。<br><br>商品材質<br>棉 100%', '寬鬆版型。'],
            'GU 的產地標示' => ['寬鬆版型。商品產地：越南', '寬鬆版型。'],
            '前一句用全形驚嘆號結尾' => ['超彈力！商品產地：越南', '超彈力！'],
            '前一句用全形問號結尾' => ['好穿嗎？請參閱商品材質欄位的使用比例。', '好穿嗎？'],
            '前一句用半形驚嘆號結尾' => ['Stretch! 商品產地：越南', 'Stretch!'],
        ];
    }

    /**
     * 官網制式標示從所在那句的句首切掉，不留「請參閱」這種半句
     *
     * @dataProvider officialLabelProvider
     */
    public function test_description_drops_the_official_labels_without_leaving_half_a_sentence(string $instruction, string $expected): void
    {
        $this->assertSame($expected, $this->presenter->getDescriptionBody($instruction));
    }
}
