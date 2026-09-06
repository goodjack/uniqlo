<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorites_page_is_not_indexed(): void
    {
        $response = $this->get(route('favorites.index'));

        $response->assertOk();
        // 內容只存在使用者的瀏覽器，沒有東西可以索引
        $response->assertSee('noindex', false);
    }

    public function test_returns_cards_for_the_given_product_codes(): void
    {
        $this->createProduct(['product_code' => 'u001', 'name' => '羽絨外套']);
        $this->createProduct(['product_code' => 'u002', 'name' => '圓領T恤']);

        $response = $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 'u001']],
        ]);

        $response->assertOk();
        $response->assertSee('羽絨外套');
        $response->assertDontSee('圓領T恤');
    }

    /**
     * 這一頁不吐價格。
     */
    public function test_cards_never_expose_any_price(): void
    {
        $this->createProduct(['product_code' => 'u001', 'name' => '羽絨外套', 'min_price' => 1990]);

        $content = $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 'u001']],
        ])->assertOk()->getContent();

        $this->assertStringNotContainsString('1990', $content);
    }

    /**
     * Tocas 2.3.3 沒有 times 這個 icon class，移除鈕會變成空白方塊；
     * 正確 class 是 close（tocas.css 有 i.icon.close:before{content:"\f00d"}）。
     */
    public function test_remove_button_uses_an_icon_class_that_tocas_actually_defines(): void
    {
        $this->createProduct(['product_code' => 'u001', 'name' => '羽絨外套']);

        $content = $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 'u001']],
        ])->assertOk()->getContent();

        $this->assertStringContainsString('close icon', $content);
        $this->assertStringNotContainsString('times icon', $content);
    }

    /**
     * 使用者要知道的是「這件現在特價」，那是卡片上的既有標籤在講的事。
     */
    public function test_cards_still_show_the_product_labels(): void
    {
        $this->createProduct([
            'product_code' => 'u001',
            'name' => '羽絨外套',
            'identity' => '["concessional_rate"]',
        ]);

        $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 'u001']],
        ])->assertOk()->assertSee('特價商品');
    }

    /**
     * 收藏頁要照使用者自己的收藏順序顯示，不是資料庫的順序。
     */
    public function test_cards_follow_the_order_of_the_requested_codes(): void
    {
        $this->createProduct(['product_code' => 'u001', 'name' => '先建立的']);
        $this->createProduct(['product_code' => 'u002', 'name' => '後建立的']);

        $content = $this->postJson(route('favorites.cards'), [
            'items' => [
                ['brand' => 'UNIQLO', 'code' => 'u002'],
                ['brand' => 'UNIQLO', 'code' => 'u001'],
            ],
        ])
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($content, '先建立的'),
            strpos($content, '後建立的'),
        );
    }

    /**
     * 商品可能在收藏之後下架，那筆就從結果裡消失，不該讓整個請求失敗。
     */
    public function test_unknown_codes_are_skipped(): void
    {
        $this->createProduct(['product_code' => 'u001', 'name' => '還在的商品']);

        $response = $this->postJson(route('favorites.cards'), [
            'items' => [
                ['brand' => 'UNIQLO', 'code' => 'u001'],
                ['brand' => 'UNIQLO', 'code' => 'u-gone'],
            ],
        ]);

        $response->assertOk();
        $response->assertSee('還在的商品');
    }

    /**
     * 兩家共用同一組商品編號——u0000000053204 在 UNIQLO 是一條褲子、在 GU 是
     * 一件家居服。只用編號查會撈到另一家的商品。
     */
    public function test_the_same_product_code_in_both_brands_does_not_cross_over(): void
    {
        $this->createProduct(['brand' => 'UNIQLO', 'product_code' => 'u053204', 'name' => 'UNIQLO 的褲子']);
        $this->createProduct(['brand' => 'GU', 'product_code' => 'u053204', 'name' => 'GU 的家居服']);

        $response = $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'GU', 'code' => 'u053204']],
        ]);

        $response->assertOk();
        $response->assertSee('GU 的家居服');
        $response->assertDontSee('UNIQLO 的褲子');
    }

    public function test_items_are_required(): void
    {
        $this->postJson(route('favorites.cards'), [])->assertUnprocessable();
    }

    public function test_an_unknown_brand_is_rejected(): void
    {
        $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'MUJI', 'code' => 'u001']],
        ])->assertUnprocessable();
    }

    public function test_too_many_items_are_rejected(): void
    {
        $items = array_map(fn (int $i) => ['brand' => 'UNIQLO', 'code' => "u{$i}"], range(1, 101));

        $this->postJson(route('favorites.cards'), ['items' => $items])->assertUnprocessable();
    }

    private function createProduct(array $attributes): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => '測試商品',
            'code' => '450001',
            'sex' => '男裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
        ], $attributes)));
    }
}
