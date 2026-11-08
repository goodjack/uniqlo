<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Http\Middleware\SetEdgeCacheHeaders;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 整站不發 cookie，公開頁面交給 CDN 快取。只要回應帶 Set-Cookie，CDN 就不會存；
 * 要是存了，某個人的 cookie 會發給下一個人。
 */
class EdgeCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 可快取路由的網址參數，依參數名稱給一個存在的值。可快取的路由從路由表找，
     * 新增的路由用到新參數時，這裡沒有對應的值就會失敗，提醒補上。
     */
    private const ROUTE_PARAMETERS = [
        'product' => '4199990001',
        'uniqlo_product_code' => 'u990001',
        'gu_product_code' => 'g990002',
        'brand' => 'uniqlo',
        'code' => 'all_women-tops',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $category = HmallCategory::create([
            'brand' => 'UNIQLO',
            'code' => 'all_women-tops',
            'name' => '上衣類',
            'parent_code' => null,
            'level' => CategoryLevel::One->value,
        ]);

        $this->createHmallProduct('UNIQLO', 'u990001')->categories()->attach($category->id, ['sort' => '001']);
        $this->createHmallProduct('GU', 'g990002');
        Product::unguarded(fn () => Product::create(['id' => '4199990001', 'name' => '男裝 圓領T恤', 'price' => 390]));
    }

    public function test_every_cacheable_route_returns_a_cacheable_page_without_cookies(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => in_array('edge-cache', $route->gatherMiddleware(), true));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->uri());

            $parameters = array_map(fn (string $name) => self::ROUTE_PARAMETERS[$name], $route->parameterNames());
            $response = $this->get(route($route->getName(), $parameters))->assertOk();
            $cacheControl = (string) $response->headers->get('Cache-Control');

            $this->assertSame([], $response->headers->getCookies(), $route->uri());
            $this->assertTrue($response->headers->hasCacheControlDirective('public'), "{$route->uri()}: {$cacheControl}");
            $this->assertSame((string) SetEdgeCacheHeaders::MAX_AGE, $response->headers->getCacheControlDirective('max-age'));
            $this->assertSame((string) SetEdgeCacheHeaders::S_MAXAGE, $response->headers->getCacheControlDirective('s-maxage'));
            $this->assertSame(SetEdgeCacheHeaders::CACHE_TAG, $response->headers->get('Cache-Tag'), $route->uri());
        }
    }

    public function test_other_responses_carry_no_cookie_and_stay_uncacheable(): void
    {
        $responses = [
            '收藏頁' => $this->get(route('favorites.index'))->assertOk(),
            '收藏卡片' => $this->postJson(route('favorites.cards'), ['items' => [['brand' => 'UNIQLO', 'code' => 'u990001']]])->assertOk(),
            '搜尋表單' => $this->get(route('search.index', ['query' => '短袖'])),
            '搜尋網址' => $this->get(route('search.show', ['query' => '短袖'])),
            // 錯誤頁存進 CDN 會在修好後還留著
            '找不到的商品頁' => $this->get('/hmall-products/u000000')->assertNotFound(),
            '找不到的舊商品頁' => $this->get('/products/4199999999')->assertNotFound(),
            '找不到的分類頁' => $this->get('/categories/uniqlo/no-such-category')->assertNotFound(),
            '沒有這個網址' => $this->get('/no-such-page')->assertNotFound(),
        ];

        foreach ($responses as $label => $response) {
            $this->assertSame([], $response->headers->getCookies(), $label);
            $this->assertSame('no-cache, private', $response->headers->get('Cache-Control'), $label);
        }
    }

    /**
     * 最後一道防線：之後有人在可快取的頁面發了 cookie，回應就不標成可快取。
     */
    public function test_a_cacheable_route_that_sets_a_cookie_is_not_marked_cacheable(): void
    {
        Route::middleware('edge-cache')->get('/__edge-cache-test', fn () => response('ok')->cookie('visited', '1'));

        $response = $this->get('/__edge-cache-test')->assertOk();

        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }

    /**
     * CDN 的快取鍵只看網址，不看請求內容。帶內容的 GET（例如 JSON）在 Laravel 會被當成輸入讀進去，
     * 頁碼、篩選可能跟網址不同；這種回應存起來，會讓同一個網址的其他人看到錯的頁面。
     */
    public function test_a_get_request_with_a_body_is_not_marked_cacheable(): void
    {
        Route::middleware('edge-cache')->get('/__edge-cache-test', fn () => response('ok'));

        $response = $this->call('GET', '/__edge-cache-test', server: ['CONTENT_TYPE' => 'application/json'], content: '{"page":2}')
            ->assertOk();

        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }

    private function createHmallProduct(string $brand, string $productCode): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => $brand,
            'name' => '短袖上衣',
            'product_name' => '短袖上衣',
            'code' => substr($productCode, 1),
            'product_code' => $productCode,
            'sex' => '女裝',
            'gender' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
            'lowest_record_price' => 990,
            'highest_record_price' => 990,
        ]));
    }
}
