<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_q_filters_the_rendered_list_by_name(): void
    {
        $this->seedSaleProduct(['name' => '男女適穿 牛仔超寬版短褲', 'code' => '359225', 'sex' => '男裝']);
        $this->seedSaleProduct(['name' => 'AIRism 圓領T恤', 'code' => '474238', 'sex' => '男裝']);

        $content = $this->get(route('lists.sale').'?q='.urlencode('短褲'))->assertOk()->getContent();

        $this->assertStringContainsString('牛仔超寬版短褲', $content);
        $this->assertStringNotContainsString('AIRism', $content);
    }

    public function test_q_filters_the_rendered_list_by_code(): void
    {
        $this->seedSaleProduct(['name' => '牛仔超寬版短褲', 'code' => '359225', 'sex' => '男裝']);
        $this->seedSaleProduct(['name' => 'AIRism 圓領T恤', 'code' => '474238', 'sex' => '男裝']);

        $content = $this->get(route('lists.sale').'?q=474238')->assertOk()->getContent();

        $this->assertStringContainsString('AIRism', $content);
        $this->assertStringNotContainsString('牛仔超寬版短褲', $content);
    }

    public function test_the_search_input_enables_instant_filtering(): void
    {
        $this->seedSaleProduct(['name' => '牛仔超寬版短褲', 'code' => '359225', 'sex' => '男裝']);

        $content = $this->get(route('lists.sale'))->assertOk()->getContent();

        $this->assertStringContainsString('data-instant-filter', $content);
    }

    public function test_the_instant_filter_knows_the_server_query_and_has_a_hint_slot(): void
    {
        $this->seedSaleProduct(['name' => '短袖 圓領T恤', 'code' => '474238', 'sex' => '男裝']);

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->get(route('lists.sale').'?q='.urlencode('短袖 T恤'))->getContent());
        $xpath = new \DOMXPath($dom);

        $this->assertSame('短袖 T恤', $xpath->query('//input[@data-instant-filter]/@value')->item(0)->nodeValue);

        $hint = $xpath->query('//*[@data-instant-hint]')->item(0);

        $this->assertNotNull($hint);
        $this->assertTrue($hint->hasAttribute('hidden'));
    }

    public function test_q_and_brand_and_sort_and_tags_all_apply_together(): void
    {
        // 預設照折扣排，這件折得多會排前面；照價格排則要排到後面
        $this->seedSaleProduct([
            'name' => 'UNIQLO 期間限定短褲 貴',
            'brand' => 'UNIQLO',
            'identity' => json_encode(['concessional_rate', 'time_doptimal']),
            'min_price' => 990,
            'highest_record_price' => 2000,
        ]);
        $this->seedSaleProduct([
            'name' => 'UNIQLO 期間限定短褲 便宜',
            'brand' => 'UNIQLO',
            'identity' => json_encode(['concessional_rate', 'time_doptimal']),
            'min_price' => 390,
            'highest_record_price' => 400,
        ]);
        $this->seedSaleProduct(['name' => 'UNIQLO 一般特價短褲', 'brand' => 'UNIQLO', 'min_price' => 590]);
        $this->seedSaleProduct([
            'name' => 'GU 期間限定短褲',
            'brand' => 'GU',
            'identity' => json_encode(['concessional_rate', 'time_doptimal']),
        ]);
        $this->seedSaleProduct([
            'name' => 'UNIQLO 期間限定上衣',
            'brand' => 'UNIQLO',
            'identity' => json_encode(['concessional_rate', 'time_doptimal']),
        ]);

        $content = $this->get(route('lists.sale').'?q='.urlencode('短褲').'&brand=UNIQLO&sort=price-asc&tags[]=limited-offer')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('UNIQLO 一般特價短褲', $content);
        $this->assertStringNotContainsString('GU 期間限定短褲', $content);
        $this->assertStringNotContainsString('UNIQLO 期間限定上衣', $content);
        $cheap = strpos($content, 'UNIQLO 期間限定短褲 便宜');
        $pricey = strpos($content, 'UNIQLO 期間限定短褲 貴');
        // 先確認兩件都在，否則篩錯把其中一件濾掉時，strpos 回 false 也會讓排序比較通過
        $this->assertNotFalse($cheap);
        $this->assertNotFalse($pricey);
        $this->assertLessThan($pricey, $cheap);
    }

    /**
     * 副標與性別分頁的件數都是篩完之後才算的，不能維持篩選前的總數。
     */
    public function test_the_subtitle_and_gender_counts_follow_the_filtered_result(): void
    {
        $this->seedSaleProduct(['name' => '男裝短褲', 'code' => '444444', 'sex' => '男裝']);
        $this->seedSaleProduct(['name' => '女裝短褲', 'code' => '555555', 'sex' => '女裝']);
        $this->seedSaleProduct(['name' => '男裝上衣', 'code' => '666666', 'sex' => '男裝']);

        $content = $this->get(route('lists.sale').'?q='.urlencode('短褲'))->assertOk()->getContent();

        $this->assertStringContainsString('2 件符合「短褲」', $content);

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$content);
        $xpath = new \DOMXPath($dom);

        $menCount = $xpath->query("//h2[@data-gender-heading='men']//div[contains(@class, 'sub')][contains(@class, 'header')]");

        $this->assertSame(1, $menCount->length);
        $this->assertSame('共 1 件', trim($menCount->item(0)->textContent));
    }

    private function seedSaleProduct(array $overrides = []): HmallProduct
    {
        static $sequence = 0;
        $sequence++;

        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => "測試商品 {$sequence}",
            'code' => "9900{$sequence}",
            'product_code' => "u9900{$sequence}",
            'sex' => '男裝',
            'gender' => '男裝',
            // Sale 標籤的判準之一：identity 裡有 concessional_rate
            'identity' => json_encode(['concessional_rate']),
            'stock' => 'Y',
            'min_price' => 990,
        ], $overrides)));
    }
}
