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

    /**
     * 「只看優惠中」篩選鈕靠這個屬性判斷，不解析畫面上的文字或顏色
     * （見 HmallProductPresenter::isOnOffer()）。
     */
    public function test_a_product_on_sale_is_marked_as_on_offer(): void
    {
        $this->createProduct(['product_code' => 'u001', 'identity' => '["concessional_rate"]']);

        $content = $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 'u001']],
        ])->assertOk()->getContent();

        $this->assertStringContainsString('data-on-offer="1"', $content);
    }

    /**
     * 已售罄的商品即使同時掛著特價標籤，也不算優惠中——現在買不到。
     */
    public function test_a_stockout_product_on_sale_is_not_marked_as_on_offer(): void
    {
        $this->createProduct([
            'product_code' => 'u001',
            'identity' => '["concessional_rate"]',
            'stock' => 'N',
        ]);

        $content = $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 'u001']],
        ])->assertOk()->getContent();

        $this->assertStringContainsString('data-on-offer="0"', $content);
        $this->assertStringNotContainsString('data-on-offer="1"', $content);
    }

    /**
     * 新款商品不是價格優惠，不該被標成優惠中。
     */
    public function test_a_new_arrival_is_not_marked_as_on_offer(): void
    {
        $this->createProduct(['product_code' => 'u001', 'identity' => '["new_product"]']);

        $content = $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 'u001']],
        ])->assertOk()->getContent();

        $this->assertStringContainsString('data-on-offer="0"', $content);
    }

    public function test_items_are_required(): void
    {
        $this->postJson(route('favorites.cards'), [])->assertUnprocessable();
    }

    /**
     * 少了整個 items 鍵跟給一個空陣列，走的是同一條 required 規則，但沒有
     * 測試釘住空陣列這個形狀（PR #76 審查留言 4057184152 第 1 點）。
     */
    public function test_an_empty_items_array_is_rejected(): void
    {
        $this->postJson(route('favorites.cards'), ['items' => []])->assertUnprocessable();
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

    /**
     * 只測過 101 筆被擋，沒測過剛好卡在上限的 100 筆會通過——把 max 規則
     * 改成 max:99 不會有任何測試變紅（PR #76 審查留言 4057184152 第 2 點）。
     */
    public function test_exactly_the_maximum_item_count_is_accepted(): void
    {
        $items = array_map(fn (int $i) => ['brand' => 'UNIQLO', 'code' => "u{$i}"], range(1, 100));

        $this->postJson(route('favorites.cards'), ['items' => $items])->assertOk();
    }

    /**
     * code 的 string 規則沒有測試釘住，拿掉它整份測試照樣全綠
     * （PR #76 審查留言 4057184152 第 3 點）。
     */
    public function test_a_numeric_code_is_rejected(): void
    {
        $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 123]],
        ])->assertUnprocessable();
    }

    public function test_an_array_code_is_rejected(): void
    {
        $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => ['u001']]],
        ])->assertUnprocessable();
    }

    /**
     * max:191 沒有測試釘住（PR #76 審查留言 4057184152 第 4 點）。
     */
    public function test_a_code_over_the_max_length_is_rejected(): void
    {
        $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => str_repeat('a', 192)]],
        ])->assertUnprocessable();
    }

    /**
     * 同一組品牌加編號重複出現在 items 裡，現在的行為是照 items 的筆數渲染
     * 同樣的卡片、不去重。這裡判斷這個行為合理、直接釘住，不是新設計：
     *
     * - 真的瀏覽器用戶端（favorites.js）不可能送出重複——收藏清單存在
     *   localStorage 裡是一個以 "brand:code" 當 key 的物件，同一組品牌加
     *   編號本來就只能有一筆。要送出重複的 items，只能直接打這支 API，
     *   繞過前端。
     * - getByBrandAndProductCodes() 對重複的 items 沒有額外查詢成本：
     *   whereIn 本身就會把重複的 code 去重，只查一次、只抓一列，重複的
     *   卡片只是同一個 Eloquent model 被渲染了 100 次，不是查了 100 次。
     * - 「照 items 的順序、一筆換一張卡」是這支服務本來就有的合約
     *   （test_cards_follow_the_order_of_the_requested_codes 已經釘住順序
     *   要跟著 items 走），重複輸入就重複輸出是這個合約直接的結果，不是
     *   意外的邊角案例。
     *
     * 上限已經有 MAX_CODES=100 擋著，最壞情況也只是這一次請求收到 100 張
     * 一樣的卡片，不影響其他人、不會多洩漏資料（PR #76 審查留言
     * 4057184152 第 5 點）。
     */
    public function test_duplicate_items_render_a_card_for_each_occurrence(): void
    {
        $this->createProduct(['product_code' => 'u001', 'name' => '羽絨外套']);

        $items = array_fill(0, 100, ['brand' => 'UNIQLO', 'code' => 'u001']);

        $content = $this->postJson(route('favorites.cards'), ['items' => $items])
            ->assertOk()
            ->getContent();

        // data-favorite-key 是每一列唯一的識別屬性，一列一次，不像品名會在
        // alt 跟標題各出現一次、算起來要乘二
        $this->assertSame(100, substr_count($content, 'data-favorite-key="UNIQLO:u001"'));
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
