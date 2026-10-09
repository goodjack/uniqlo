<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_an_empty_items_array_is_rejected(): void
    {
        $this->postJson(route('favorites.cards'), ['items' => []])->assertUnprocessable();
    }

    public function test_too_many_items_are_rejected(): void
    {
        $items = array_map(fn (int $i) => ['brand' => 'UNIQLO', 'code' => "u{$i}"], range(1, 101));

        $this->postJson(route('favorites.cards'), ['items' => $items])->assertUnprocessable();
    }

    public function test_exactly_the_maximum_item_count_is_accepted(): void
    {
        $items = array_map(fn (int $i) => ['brand' => 'UNIQLO', 'code' => "u{$i}"], range(1, 100));

        $this->postJson(route('favorites.cards'), ['items' => $items])->assertOk();
    }

    /**
     * 收藏存在使用者的瀏覽器，壞掉的那筆略過，其他照常回卡片，不讓整份收藏載入不到。
     */
    #[DataProvider('malformedItems')]
    public function test_a_malformed_item_is_skipped_while_the_rest_still_render(mixed $badItem): void
    {
        $this->createProduct(['product_code' => 'u001', 'name' => '羽絨外套']);

        $this->postJson(route('favorites.cards'), [
            'items' => [$badItem, ['brand' => 'UNIQLO', 'code' => 'u001']],
        ])
            ->assertOk()
            ->assertSee('羽絨外套');
    }

    public static function malformedItems(): array
    {
        return [
            'unknown brand' => [['brand' => 'MUJI', 'code' => 'u001']],
            'lowercase brand' => [['brand' => 'uniqlo', 'code' => 'u001']],
            'numeric code' => [['brand' => 'UNIQLO', 'code' => 123]],
            'array code' => [['brand' => 'UNIQLO', 'code' => ['u001']]],
            'code over the max length' => [['brand' => 'UNIQLO', 'code' => str_repeat('a', 192)]],
            'empty code' => [['brand' => 'UNIQLO', 'code' => '']],
            'missing brand' => [['code' => 'u001']],
            'not an object' => ['UNIQLO:u001'],
            'null' => [null],
        ];
    }

    /**
     * 重複的品牌加編號照筆數渲染、不去重：前端的收藏以 brand:code 為 key 不會
     * 送出重複，whereIn 也只查一次，最壞就是這次請求收到 100 張一樣的卡片。
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

    /**
     * 測試環境預設會跳過 CSRF 檢查，這裡把那個捷徑關掉，確認換卡片這支在
     * 正式環境也不需要 token。
     */
    public function test_cards_endpoint_does_not_require_a_csrf_token(): void
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };

        $request = Request::create('/favorites/cards', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $response = $middleware->handle($request, fn () => new Response('passed'));

        $this->assertSame('passed', $response->getContent());
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
