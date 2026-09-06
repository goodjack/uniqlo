<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 清單頁「在這個清單裡找」的端到端驗收：q 這個 GET 參數真的能篩到
 * ListService::filterHmallProducts() 預熱好的 Collection，不是只有表單
 * 長對而已（那部分留給 ListFilterTest 跟 ListServiceTest）。
 */
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

    public function test_q_and_brand_and_sort_and_tags_all_apply_together(): void
    {
        $this->seedSaleProduct([
            'name' => 'UNIQLO 特價短褲',
            'code' => '111111',
            'brand' => 'UNIQLO',
            'sex' => '男裝',
            'min_price' => 590,
        ]);
        $this->seedSaleProduct([
            'name' => 'GU 特價短褲',
            'code' => '222222',
            'brand' => 'GU',
            'sex' => '男裝',
            'min_price' => 390,
        ]);
        $this->seedSaleProduct([
            'name' => 'UNIQLO 特價上衣',
            'code' => '333333',
            'brand' => 'UNIQLO',
            'sex' => '男裝',
            'min_price' => 990,
        ]);

        $content = $this->get(route('lists.sale').'?q='.urlencode('短褲').'&brand=UNIQLO&sort=price-asc')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('UNIQLO 特價短褲', $content);
        $this->assertStringNotContainsString('GU 特價短褲', $content);
        $this->assertStringNotContainsString('UNIQLO 特價上衣', $content);
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

        $menCount = $xpath->query("//h2[contains(@class, 'uq-h2')][starts-with(normalize-space(), '男裝')]//span[contains(@class, 'uq-count')]");

        $this->assertSame(1, $menCount->length);
        $this->assertSame('1 件', trim($menCount->item(0)->textContent));
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
