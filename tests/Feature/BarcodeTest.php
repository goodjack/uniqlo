<?php

namespace Tests\Feature;

use App\Models\Barcode;
use App\Models\HmallProduct;
use App\Models\JapanProduct;
use App\Services\BarcodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BarcodeTest extends TestCase
{
    use RefreshDatabase;

    private const TW_UNIQLO = 'https://tw-uniqlo.test/sku/*';

    private const TW_GU = 'https://tw-gu.test/sku/*';

    private const JP_UNIQLO = 'https://jp-uniqlo.test/lookup/*';

    private const JP_GU = 'https://jp-gu.test/lookup/*';

    /** 台灣 UNIQLO 吊牌，吊牌編號 482306 */
    private const TW_BARCODE = '2000216727355';

    /** 日本 UNIQLO 吊牌，吊牌編號 478279 */
    private const JP_BARCODE = '2000203620522';

    /** 檢查碼正確、店內碼，但官網都查不到 */
    private const UNKNOWN_BARCODE = '2000000000008';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'barcode.enabled' => true,
            'barcode.headers.tw' => '{"X-Test":"tw"}',
            'barcode.headers.jp' => '{"X-Test":"jp"}',
            'barcode.limits.per_minute' => 10,
            'barcode.limits.daily_upstream' => 400,
            'barcode.limits.daily_upstream_per_client' => 40,
            'uniqlo.api.sku_lookup.tw' => 'https://tw-uniqlo.test/sku/{barcode}',
            'gu.api.sku_lookup.tw' => 'https://tw-gu.test/sku/{barcode}',
            'uniqlo.api.sku_lookup.jp' => 'https://jp-uniqlo.test/lookup/{barcode}',
            'gu.api.sku_lookup.jp' => 'https://jp-gu.test/lookup/{barcode}',
        ]);

        Http::preventStrayRequests();
    }

    public static function rejectedBarcodes(): array
    {
        return [
            '檢查碼錯' => ['2000216727356', 'invalid'],
            '長度不對' => ['200021672735', 'invalid'],
            '一般商品條碼（台灣）' => ['4710000000004', 'not_product'],
        ];
    }

    #[DataProvider('rejectedBarcodes')]
    public function test_obviously_wrong_barcodes_never_reach_the_official_sites(string $barcode, string $status): void
    {
        Http::fake();

        $this->getJson(route('barcodes.show', $barcode))
            ->assertOk()
            ->assertJson(['status' => $status, 'barcode' => $barcode]);

        Http::assertNothingSent();
        $this->assertFalse(Cache::has(BarcodeService::NOT_FOUND_CACHE_PREFIX.$barcode));
    }

    public function test_a_stored_barcode_does_not_ask_the_official_sites_again(): void
    {
        Http::fake();
        $product = $this->createProduct(['product_code' => 'u0000000053917', 'code' => '482306']);
        $this->createBarcode(['barcode' => self::TW_BARCODE, 'product_code' => 'u0000000053917']);

        $this->getJson(route('barcodes.show', self::TW_BARCODE))
            ->assertOk()
            ->assertJson(['status' => 'found', 'url' => $product->route_url, 'code' => '482306']);

        Http::assertNothingSent();
    }

    public function test_a_taiwan_barcode_is_stored_and_leads_to_the_product_page(): void
    {
        Http::fake([self::TW_UNIQLO => Http::response($this->taiwanFound())]);
        $product = $this->createProduct(['product_code' => 'u0000000053917', 'code' => '482306']);

        $this->getJson(route('barcodes.show', self::TW_BARCODE))
            ->assertOk()
            ->assertJson([
                'status' => 'found',
                'url' => $product->route_url,
                'barcode' => self::TW_BARCODE,
                'code' => '482306',
            ]);

        $this->assertDatabaseHas('barcodes', [
            'barcode' => self::TW_BARCODE,
            'region' => 'tw',
            'brand' => 'UNIQLO',
            'product_code' => 'u0000000053917',
        ]);
        $this->assertSame(['482306'], Barcode::first()->codes);

        // 設定裡的標頭要原樣帶上
        Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('X-Test', 'tw'));
        Http::assertSentCount(1);
    }

    public function test_a_gu_barcode_is_found_after_uniqlo_comes_back_empty(): void
    {
        Http::fake([
            self::TW_UNIQLO => Http::response($this->taiwanEmpty()),
            self::TW_GU => Http::response([
                'success' => true,
                'resp' => [['sku' => 'u0000000010060002', 'goods_num' => '352745000', 'spu' => 'u0000000010060']],
                'total' => 1,
            ]),
        ]);
        $product = $this->createProduct(['brand' => 'GU', 'product_code' => 'u0000000010060', 'code' => '352745']);

        $this->getJson(route('barcodes.show', '2200071521093'))
            ->assertOk()
            ->assertJson(['status' => 'found', 'url' => $product->route_url, 'code' => '352745']);

        $this->assertDatabaseHas('barcodes', ['barcode' => '2200071521093', 'brand' => 'GU']);
        Http::assertSentCount(2);
    }

    public function test_a_japan_barcode_leads_to_the_japan_section_of_the_taiwan_product(): void
    {
        Http::fake([
            self::TW_UNIQLO => Http::response($this->taiwanEmpty()),
            self::TW_GU => Http::response($this->taiwanEmpty()),
            self::JP_UNIQLO => Http::response($this->japanFound()),
        ]);
        $product = $this->createProduct(['product_code' => 'u0000000054000', 'code' => '478279']);
        JapanProduct::unguarded(fn () => JapanProduct::create([
            'l1Id' => '478279',
            'brand' => 'UNIQLO',
            'product_id' => 'E470077-000',
            'name' => 'シームレスダウンパーカ',
        ]));

        $this->getJson(route('barcodes.show', self::JP_BARCODE))
            ->assertOk()
            ->assertJson([
                'status' => 'found',
                'url' => $product->route_url.'#japan',
                'code' => '478279',
            ]);

        $this->assertDatabaseHas('barcodes', [
            'barcode' => self::JP_BARCODE,
            'region' => 'jp',
            'brand' => 'UNIQLO',
            'product_code' => 'E470077-000',
        ]);
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'jp-uniqlo.test')
            && $request->hasHeader('X-Test', 'jp'));
        Http::assertSentCount(3);
    }

    public function test_a_japan_barcode_without_japan_data_on_the_site_skips_the_anchor(): void
    {
        Http::fake();
        $product = $this->createProduct(['product_code' => 'u0000000054000', 'code' => '478279']);
        $this->createJapanBarcode();

        $this->getJson(route('barcodes.show', self::JP_BARCODE))
            ->assertOk()
            ->assertJson(['status' => 'found', 'url' => $product->route_url]);
    }

    public function test_a_japan_barcode_falls_back_to_its_other_codes(): void
    {
        Http::fake();
        $product = $this->createProduct(['product_code' => 'u0000000047000', 'code' => '470077']);
        $this->createJapanBarcode();

        $this->getJson(route('barcodes.show', self::JP_BARCODE))
            ->assertOk()
            ->assertJson(['status' => 'found', 'url' => $product->route_url, 'code' => '470077']);
    }

    public function test_among_products_sharing_a_code_the_in_stock_newest_one_wins(): void
    {
        Http::fake();
        $this->createProduct(['product_code' => 'u-old', 'code' => '478279']);
        $wanted = $this->createProduct(['product_code' => 'u-new', 'code' => '478279']);
        $this->createProduct(['product_code' => 'u-stockout', 'code' => '478279', 'stockout_at' => now()]);
        // 另一個品牌的同編號不算
        $this->createProduct(['brand' => 'GU', 'product_code' => 'g-same-code', 'code' => '478279']);
        $this->createJapanBarcode();

        $this->getJson(route('barcodes.show', self::JP_BARCODE))
            ->assertOk()
            ->assertJson(['status' => 'found', 'url' => $wanted->route_url]);
    }

    public function test_a_taiwan_product_missing_from_the_site_links_to_the_official_page(): void
    {
        Http::fake([self::TW_UNIQLO => Http::response($this->taiwanFound())]);

        $this->getJson(route('barcodes.show', self::TW_BARCODE))
            ->assertOk()
            ->assertJson([
                'status' => 'not_listed',
                'url' => 'https://www.uniqlo.com/tw/zh_TW/product-detail.html?productCode=u0000000053917',
                'code' => '482306',
                'brand' => 'UNIQLO',
            ]);

        // 網站還沒收錄也要存，官網那一側的事實不會變
        $this->assertDatabaseHas('barcodes', ['barcode' => self::TW_BARCODE]);
    }

    public function test_a_japan_product_missing_from_the_site_links_to_the_japan_official_page(): void
    {
        Http::fake();
        $this->createJapanBarcode(['brand' => 'GU']);

        $this->getJson(route('barcodes.show', self::JP_BARCODE))
            ->assertOk()
            ->assertJson([
                'status' => 'not_listed',
                'url' => 'https://www.gu-global.com/jp/ja/products/E470077-000',
                'code' => '478279',
                'brand' => 'GU',
            ]);
    }

    public function test_a_barcode_nobody_knows_is_remembered_for_a_day_only(): void
    {
        Http::fake([
            self::TW_UNIQLO => Http::response($this->taiwanEmpty()),
            self::TW_GU => Http::response($this->taiwanEmpty()),
            self::JP_UNIQLO => Http::response($this->japanEmpty()),
            self::JP_GU => Http::response($this->japanEmpty()),
        ]);

        $this->getJson(route('barcodes.show', self::UNKNOWN_BARCODE))
            ->assertOk()
            ->assertJson(['status' => 'not_product', 'barcode' => self::UNKNOWN_BARCODE]);
        Http::assertSentCount(4);
        $this->assertDatabaseCount('barcodes', 0);

        $this->travel(23)->hours();
        $this->getJson(route('barcodes.show', self::UNKNOWN_BARCODE))->assertJson(['status' => 'not_product']);
        Http::assertSentCount(4);

        $this->travel(2)->hours();
        $this->getJson(route('barcodes.show', self::UNKNOWN_BARCODE))->assertJson(['status' => 'not_product']);
        Http::assertSentCount(8);
    }

    public static function upstreamFailures(): array
    {
        return [
            '連不上' => [fn () => throw new ConnectionException('timed out')],
            '被擋' => [fn () => Http::response('<html>Access Denied</html>', 403)],
            '回了網頁不是 JSON' => [fn () => Http::response('<html></html>', 200)],
        ];
    }

    #[DataProvider('upstreamFailures')]
    public function test_an_upstream_failure_is_neither_stored_nor_cached(\Closure $failure): void
    {
        Http::fake([
            self::TW_UNIQLO => Http::response($this->taiwanEmpty()),
            self::TW_GU => $failure,
        ]);

        $this->getJson(route('barcodes.show', self::UNKNOWN_BARCODE))
            ->assertOk()
            ->assertJson(['status' => 'unavailable', 'barcode' => self::UNKNOWN_BARCODE]);

        $this->assertDatabaseCount('barcodes', 0);
        $this->assertFalse(Cache::has(BarcodeService::NOT_FOUND_CACHE_PREFIX.self::UNKNOWN_BARCODE));
        // 出錯就停，不會接著問日本
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'jp-'));
    }

    public static function ambiguousAnswers(): array
    {
        return [
            '台灣 success 不是 true' => [self::TW_UNIQLO, ['success' => false, 'resp' => []]],
            '台灣沒有 resp' => [self::TW_UNIQLO, ['success' => true]],
            '台灣 resp 是空的' => [self::TW_UNIQLO, ['success' => true, 'resp' => [], 'total' => 0]],
            '台灣 resp[0] 沒有 spu' => [self::TW_UNIQLO, ['success' => true, 'resp' => [['sku' => null]]]],
            '日本 nok 但不是 33' => [self::JP_UNIQLO, ['status' => 'nok', 'error' => ['code' => 500]]],
            '日本 nok 沒有錯誤碼' => [self::JP_UNIQLO, ['status' => 'nok']],
        ];
    }

    #[DataProvider('ambiguousAnswers')]
    public function test_only_a_clear_not_found_is_remembered(string $source, array $answer): void
    {
        Http::fake(array_merge([
            self::TW_UNIQLO => Http::response($this->taiwanEmpty()),
            self::TW_GU => Http::response($this->taiwanEmpty()),
            self::JP_UNIQLO => Http::response($this->japanEmpty()),
            self::JP_GU => Http::response($this->japanEmpty()),
        ], [$source => Http::response($answer)]));

        $this->getJson(route('barcodes.show', self::UNKNOWN_BARCODE))
            ->assertOk()
            ->assertJson(['status' => 'unavailable']);

        $this->assertFalse(Cache::has(BarcodeService::NOT_FOUND_CACHE_PREFIX.self::UNKNOWN_BARCODE));
        $this->assertDatabaseCount('barcodes', 0);
    }

    public function test_one_source_is_limited_per_minute(): void
    {
        Http::fake();
        config(['barcode.limits.per_minute' => 2]);

        $this->getJson(route('barcodes.show', '4710000000004'))->assertOk();
        $this->getJson(route('barcodes.show', '4710000000004'))->assertOk();

        $this->getJson(route('barcodes.show', '4710000000004'))
            ->assertStatus(429)
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertJson(['status' => 'unavailable', 'barcode' => '4710000000004']);
    }

    public function test_the_whole_site_stops_asking_the_official_sites_after_the_daily_limit(): void
    {
        Http::fake([
            self::TW_UNIQLO => Http::response($this->taiwanEmpty()),
            self::TW_GU => Http::response($this->taiwanEmpty()),
            self::JP_UNIQLO => Http::response($this->japanEmpty()),
            self::JP_GU => Http::response($this->japanEmpty()),
        ]);
        config(['barcode.limits.daily_upstream' => 5]);

        $this->getJson(route('barcodes.show', self::UNKNOWN_BARCODE))->assertJson(['status' => 'not_product']);

        // 第二個條碼問完台灣 UNIQLO 就用完今天的 5 次
        $this->getJson(route('barcodes.show', '2000000000015'))->assertJson(['status' => 'unavailable']);
        Http::assertSentCount(5);
        $this->assertFalse(Cache::has(BarcodeService::NOT_FOUND_CACHE_PREFIX.'2000000000015'));

        $this->travelTo(now()->addDay()->startOfDay()->addMinute());
        $this->getJson(route('barcodes.show', '2000000000015'))->assertJson(['status' => 'not_product']);
    }

    public function test_the_daily_count_never_goes_past_the_limit(): void
    {
        Http::fake([self::TW_UNIQLO => Http::response($this->taiwanFound())]);
        config(['barcode.limits.daily_upstream' => 3]);
        $counter = BarcodeService::UPSTREAM_COUNTER_PREFIX.now()->toDateString();

        // 不同條碼各自問一次官網；第 4 次起不送
        foreach (['2000000000015', '2000000000022', '2000000000039', '2000000000046', '2000000000053'] as $i => $barcode) {
            $expected = $i < 3 ? 'not_listed' : 'unavailable';
            $this->getJson(route('barcodes.show', $barcode))->assertJson(['status' => $expected]);
        }

        Http::assertSentCount(3);
        $this->assertSame(3, Cache::get($counter));
    }

    public function test_one_client_cannot_use_up_the_whole_site_quota(): void
    {
        Http::fake([self::TW_UNIQLO => Http::response($this->taiwanFound())]);
        config(['barcode.limits.daily_upstream_per_client' => 2]);
        $global = BarcodeService::UPSTREAM_COUNTER_PREFIX.now()->toDateString();
        $first = ['REMOTE_ADDR' => '203.0.113.1'];

        $this->withServerVariables($first)->getJson(route('barcodes.show', '2000000000015'))->assertJson(['status' => 'not_listed']);
        $this->withServerVariables($first)->getJson(route('barcodes.show', '2000000000022'))->assertJson(['status' => 'not_listed']);
        $this->withServerVariables($first)->getJson(route('barcodes.show', '2000000000039'))->assertJson(['status' => 'unavailable']);
        $this->assertFalse(Cache::has(BarcodeService::NOT_FOUND_CACHE_PREFIX.'2000000000039'));
        // 已經存表的條碼不用問官網，用完額度一樣查得到
        $this->withServerVariables($first)->getJson(route('barcodes.show', '2000000000015'))->assertJson(['status' => 'not_listed']);
        Http::assertSentCount(2);
        // 被擋下的那次不吃全站額度
        $this->assertSame(2, Cache::get($global));

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])
            ->getJson(route('barcodes.show', '2000000000039'))
            ->assertJson(['status' => 'not_listed']);
        Http::assertSentCount(3);
        $this->assertSame(3, Cache::get($global));
    }

    public function test_a_request_that_cannot_take_the_counter_lock_is_not_sent(): void
    {
        Http::fake();
        // 模擬另一個請求正在數，而且一直沒放開
        $lock = Cache::lock(BarcodeService::UPSTREAM_LOCK, 10);
        $this->assertTrue($lock->get());

        $this->getJson(route('barcodes.show', self::UNKNOWN_BARCODE))->assertJson(['status' => 'unavailable']);

        Http::assertNothingSent();
        $lock->release();
    }

    public function test_broken_header_settings_are_treated_as_empty(): void
    {
        config(['barcode.headers.tw' => '{not json']);
        Http::fake([self::TW_UNIQLO => Http::response($this->taiwanFound())]);

        $this->getJson(route('barcodes.show', self::TW_BARCODE))->assertJson(['status' => 'not_listed']);

        Http::assertSent(fn (HttpRequest $request) => ! $request->hasHeader('X-Test'));
    }

    public function test_the_response_is_kept_out_of_search_engines(): void
    {
        Http::fake();

        $this->getJson(route('barcodes.show', '4710000000004'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_non_digits_are_not_a_barcode(): void
    {
        $this->getJson('/barcodes/abc')->assertNotFound();
    }

    public function test_the_master_switch_hides_the_icon_and_the_endpoint(): void
    {
        Http::fake();
        config(['barcode.enabled' => false]);

        $this->getJson(route('barcodes.show', self::TW_BARCODE))->assertNotFound();
        Http::assertNothingSent();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-barcode-scan', false)
            ->assertDontSee('data-placeholder-narrow', false)
            ->assertDontSee('barcode-scan.js', false);
    }

    public function test_the_icon_sits_in_the_search_box_without_visible_text_when_switched_on(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<button[^>]*data-barcode-scan[^>]*aria-label="掃吊牌條碼"[^>]*>(.*?)<\/button>/s', $content, $match));
        // 只有一個不給讀屏軟體念的圖示，沒有看得到的字
        $this->assertStringContainsString('<svg', $match[1]);
        $this->assertStringContainsString('aria-hidden="true"', $match[1]);
        $this->assertSame('', trim(strip_tags($match[1])));
        $this->assertStringContainsString('barcode-scan.js', $content);
        // 完整提示不變，窄螢幕的短提示交給前端換
        $this->assertStringContainsString('placeholder="輸入關鍵字或編號..."', $content);
        $this->assertStringContainsString('data-placeholder-narrow="關鍵字或編號"', $content);
    }

    public function test_the_icon_comes_before_the_magnifier(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($content, '<i class="search icon">'),
            strpos($content, 'data-barcode-scan'),
        );
    }

    private function taiwanFound(): array
    {
        return [
            'success' => true,
            'resp' => [['sku' => 'u0000000053917001', 'goods_num' => '482306000', 'spu' => 'u0000000053917']],
            'total' => 1,
        ];
    }

    private function taiwanEmpty(): array
    {
        return ['success' => true, 'resp' => [['sku' => null, 'goods_num' => null, 'spu' => null]], 'total' => 1];
    }

    private function japanFound(): array
    {
        return [
            'status' => 'ok',
            'result' => [
                'l1Ids' => ['478279', '470077'],
                'productId' => 'E470077-000',
                'communicationCode' => '478279-69-006-000',
            ],
        ];
    }

    private function japanEmpty(): array
    {
        return ['status' => 'nok', 'error' => ['code' => 33, 'details' => [], 'httpStatusCode' => 404]];
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

    private function createBarcode(array $attributes): Barcode
    {
        return Barcode::create(array_merge([
            'region' => 'tw',
            'brand' => 'UNIQLO',
            'product_code' => 'u0000000000000',
            'codes' => ['482306'],
        ], $attributes));
    }

    private function createJapanBarcode(array $attributes = []): Barcode
    {
        return $this->createBarcode(array_merge([
            'barcode' => self::JP_BARCODE,
            'region' => 'jp',
            'product_code' => 'E470077-000',
            'codes' => ['478279', '470077'],
        ], $attributes));
    }
}
