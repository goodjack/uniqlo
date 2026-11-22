<?php

namespace Tests\Unit\Services;

use App\Models\HmallProduct;
use App\Models\HmallProductDetail;
use App\Services\HmallProductDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HmallProductDetailServiceTest extends TestCase
{
    use RefreshDatabase;

    private bool $faked = false;

    private string $instruction = self::NEW_DESCRIPTION;

    private int $fragmentStatus = 200;

    private ?string $features = null;

    private ?string $materialCare = null;

    /** @var array<int, string> 這幾個片段回 404 */
    private array $missing = [];

    private string $sizeChart = '';

    private const DETAIL_API = 'https://api.example.com/detail';

    private const PURGE_API = 'https://api.cloudflare.com/client/v4/zones/zone-1/purge_cache';

    private const OLD_DESCRIPTION = '<div class="product-desc">觸感柔軟且帶有透膚感的保暖透膚材質。</div>';

    private const NEW_DESCRIPTION = '<div class="product-desc">觸感柔軟且具有透膚感的保暖薄透材質。<br><br>【機能與材質】<br>- 具備保暖功能。</div>';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('gu.api.v3.detail.tw', self::DETAIL_API);
        Config::set('uniqlo.api.v3.detail.tw', self::DETAIL_API);
        Config::set('app.crawler.delay', ['min' => 0, 'max' => 0]);
        Config::set('app.crawler.retry', ['times' => 2, 'sleep_min' => 0, 'sleep_max' => 0]);
        Config::set('services.cloudflare.zone_id', 'zone-1');
        Config::set('services.cloudflare.purge_token', 'token');
    }

    public function test_the_first_fetch_stores_every_image_and_fragment_and_refreshes_the_description(): void
    {
        $product = $this->createGuProduct();
        $updatedAt = $product->updated_at;
        $this->travel(1)->days();
        $this->fakeOfficialSite();

        $summary = $this->service()->fetchProducts([$product]);

        $this->assertSame(['checked' => 1, 'changed' => 1, 'missing' => 0, 'failed' => 0, 'stopped' => null, 'blocked' => false], $summary);

        $detail = $product->fresh()->detail;
        $this->assertCount(12, $detail->main_images);
        $this->assertSame([16, 0, 0, 0, 0], $detail->rating_levels);
        $this->assertSame('柬埔寨製', $detail->origin_place);
        $this->assertSame('商品機能介紹', $detail->features[0]['text']);
        $this->assertSame(['商品材質', '洗滌說明'], array_column($detail->material_care, 'title'));
        $this->assertSame(['etag' => '"abc"', 'last_modified' => 'Tue, 03 Nov 2026 03:16:59 GMT'], $detail->validators['instruction']);
        $this->assertNotNull($detail->fetched_at);
        $this->assertNotNull($detail->content_changed_at);

        $this->assertSame(self::NEW_DESCRIPTION, $product->fresh()->instruction);
        $this->assertEquals($updatedAt, $product->fresh()->updated_at);

        Http::assertSent(fn (Request $request) => $request->url() === self::DETAIL_API.'?productCode=u0000000053291&distribution=EXPRESS&type=DETAIL');
        Http::assertSent(fn (Request $request) => $request->url() === self::PURGE_API
            && $request->data() === ['files' => [$product->route_url]]);
    }

    public function test_an_unchanged_product_only_records_the_fetch_time(): void
    {
        $product = $this->createGuProduct();
        $this->fakeOfficialSite();
        $this->service()->fetchProducts([$product]);
        $firstChangedAt = $product->fresh()->detail->content_changed_at;

        $this->travel(40)->days();
        $this->fakeOfficialSite(fragmentStatus: 304);

        $summary = $this->service()->fetchProducts([$product->fresh()->load('detail')]);

        $this->assertSame(1, $summary['checked']);
        $this->assertSame(0, $summary['changed']);
        $detail = $product->fresh()->detail;
        $this->assertEquals($firstChangedAt, $detail->content_changed_at);
        $this->assertTrue($detail->fetched_at->isToday());
        $this->assertCount(12, $detail->main_images);
        $this->assertSame('商品機能介紹', $detail->features[0]['text']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'instructionH5.html')
            && $request->hasHeader('If-None-Match', '"abc"')
            && $request->hasHeader('If-Modified-Since', 'Tue, 03 Nov 2026 03:16:59 GMT'));
        // 只有第一次抓的那一次清快取
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->url() === self::PURGE_API));
    }

    public function test_a_changed_description_is_written_and_the_page_is_purged(): void
    {
        $product = $this->createGuProduct();
        $this->fakeOfficialSite(instruction: self::OLD_DESCRIPTION);
        $this->service()->fetchProducts([$product]);

        $this->fakeOfficialSite();
        $summary = $this->service()->fetchProducts([$product->fresh()->load('detail')]);

        $this->assertSame(1, $summary['changed']);
        $this->assertSame(self::NEW_DESCRIPTION, $product->fresh()->instruction);
        // 第一次抓已經清過一次，第二次內容變了要再清一次這件商品的頁面
        $purges = Http::recorded(fn (Request $request) => $request->url() === self::PURGE_API)->values();
        $this->assertCount(2, $purges);
        $this->assertSame(['files' => [$product->route_url]], $purges[1][0]->data());
    }

    public function test_an_empty_shell_from_the_official_site_keeps_the_saved_description(): void
    {
        $product = $this->createGuProduct();
        $this->fakeOfficialSite(instruction: '<div class="product-desc"></div>');

        $this->service()->fetchProducts([$product]);

        $this->assertSame(self::OLD_DESCRIPTION, $product->fresh()->instruction);
    }

    public function test_a_product_the_official_site_no_longer_has_is_scheduled_later_without_content(): void
    {
        $product = $this->createGuProduct();
        Http::fake([self::DETAIL_API.'*' => Http::response(['success' => false, 'msg' => 'not found', 'resp' => []])]);

        $summary = $this->service()->fetchProducts([$product]);

        $this->assertSame(1, $summary['missing']);
        $detail = $product->fresh()->detail;
        $this->assertNull($detail->main_images);
        $this->assertTrue($detail->next_fetch_at->isFuture());
        Http::assertSentCount(1);
    }

    public function test_a_response_that_is_not_json_counts_as_a_failure(): void
    {
        $product = $this->createGuProduct();
        Http::fake([self::DETAIL_API.'*' => Http::response('<html>請稍候</html>')]);

        $summary = $this->service()->fetchProducts([$product]);

        $this->assertSame(1, $summary['failed']);
        $this->assertNull($product->fresh()->detail->main_images);
        $this->assertSame(1, $product->fresh()->detail->failure_count);
    }

    public function test_being_blocked_stops_the_run_and_leaves_the_rest_for_next_time(): void
    {
        foreach ([403, 429] as $status) {
            HmallProductDetail::query()->delete();
            $blocked = $this->createGuProduct('u001');
            $untouched = $this->createGuProduct('u002');
            Http::fake([self::DETAIL_API.'*' => Http::response('', $status)]);

            $summary = $this->service()->fetchProducts([$blocked, $untouched]);

            $this->assertSame(1, $summary['failed']);
            $this->assertNotNull($summary['stopped']);
            $this->assertNull($untouched->fresh()->detail);
            Http::assertSentCount(1);

            HmallProduct::query()->delete();
        }
    }

    public function test_a_single_failure_is_skipped_but_consecutive_failures_stop_the_run(): void
    {
        Config::set('app.crawler.product_details.max_consecutive_failures', 2);
        $products = collect(['u001', 'u002', 'u003', 'u004', 'u005'])->map(fn ($code) => $this->createGuProduct($code));
        Http::fake([
            self::DETAIL_API.'?productCode=u001*' => Http::response('', 500),
            self::DETAIL_API.'?productCode=u003*' => Http::response('', 500),
            self::DETAIL_API.'?productCode=u004*' => Http::response('', 500),
            self::DETAIL_API.'*' => Http::response(['success' => false, 'resp' => []]),
        ]);

        $summary = $this->service()->fetchProducts($products);

        $this->assertSame(['checked' => 1, 'changed' => 0, 'missing' => 1, 'failed' => 3, 'stopped' => '連續 2 件抓取失敗', 'blocked' => false], $summary);
        $this->assertNull($products[4]->fresh()->detail);
    }

    public function test_a_failed_product_is_recorded_and_waits_so_the_next_run_starts_elsewhere(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(3));
        Config::set('app.crawler.product_details.max_consecutive_failures', 1);
        $this->createGuProduct('u001', createdDaysAgo: 0);
        $broken = $this->createGuProduct('u002', createdDaysAgo: 0);
        Http::fake([
            self::DETAIL_API.'?productCode=u002*' => Http::response('', 500),
            self::DETAIL_API.'*' => Http::response(['success' => false, 'resp' => []]),
        ]);

        $first = $this->service()->fetchDue('GU');

        $this->assertSame(1, $first['failed']);
        $detail = $broken->fresh()->detail;
        $this->assertSame(1, $detail->failure_count);
        $this->assertTrue($detail->last_failed_at->isToday());
        $this->assertTrue($detail->next_fetch_at->isTomorrow());
        $this->assertSame(['u001'], $this->service()->dueProducts('GU', 10, 10)->pluck('product_code')->all());

        $this->travel(1)->days();
        $this->service()->fetchDue('GU');
        $this->assertSame(2, $broken->fresh()->detail->failure_count);
        $this->assertEquals(now()->addDays(2)->startOfSecond(), $broken->fresh()->detail->next_fetch_at);
    }

    public function test_a_success_after_failures_clears_the_failure_count(): void
    {
        $product = $this->createGuProduct(detail: ['failure_count' => 3, 'next_fetch_at' => now()->subHour()]);
        $this->fakeOfficialSite();

        $this->service()->fetchProducts([$product->load('detail')]);

        $this->assertSame(0, $product->fresh()->detail->failure_count);
        $this->assertCount(12, $product->fresh()->detail->main_images);
    }

    public function test_being_blocked_is_not_counted_against_the_product(): void
    {
        $product = $this->createGuProduct();
        Http::fake([self::DETAIL_API.'*' => Http::response('', 403)]);

        $summary = $this->service()->fetchProducts([$product]);

        $this->assertTrue($summary['blocked']);
        $this->assertNull($product->fresh()->detail);
    }

    public function test_empty_features_or_material_care_keep_what_was_saved(): void
    {
        $product = $this->createGuProduct();
        $this->fakeOfficialSite();
        $this->service()->fetchProducts([$product]);

        $this->features = '<html><body><div class="product-features-content"></div></body></html>';
        $this->materialCare = '';
        $summary = $this->service()->fetchProducts([$product->fresh()->load('detail')]);

        $this->assertSame(0, $summary['changed']);
        $this->assertSame('商品機能介紹', $product->fresh()->detail->features[0]['text']);
        $this->assertSame('商品材質', $product->fresh()->detail->material_care[0]['title']);
    }

    public function test_a_fragment_that_suddenly_cannot_be_found_keeps_what_was_saved(): void
    {
        $product = $this->createGuProduct();
        $this->fakeOfficialSite();
        $this->service()->fetchProducts([$product]);

        $this->missing = ['features.html', 'materialCare.html'];
        $summary = $this->service()->fetchProducts([$product->fresh()->load('detail')]);

        $this->assertSame(0, $summary['changed']);
        $this->assertSame('商品機能介紹', $product->fresh()->detail->features[0]['text']);
        $this->assertSame('商品材質', $product->fresh()->detail->material_care[0]['title']);
    }

    public function test_an_empty_description_is_refetched_together_with_the_size_chart(): void
    {
        $product = $this->createGuProduct(attributes: ['instruction' => '<div class="product-desc"></div>', 'size_chart' => '']);
        $updatedAt = $product->updated_at;
        $this->sizeChart = '<table><tr><td>M</td></tr></table>';
        $this->fakeOfficialSite();

        $this->service()->fetchProducts([$product]);

        $this->assertSame(self::NEW_DESCRIPTION, $product->fresh()->instruction);
        $this->assertSame('<table><tr><td>M</td></tr></table>', $product->fresh()->size_chart);
        $this->assertEquals($updatedAt, $product->fresh()->updated_at);
    }

    public function test_a_blank_size_chart_keeps_the_saved_one_and_filled_descriptions_skip_it(): void
    {
        $empty = $this->createGuProduct('u001', attributes: ['instruction' => '<div class="product-desc"></div>', 'size_chart' => '<table>舊的</table>']);
        $filled = $this->createGuProduct('u002');
        $this->sizeChart = '<div></div>';
        $this->fakeOfficialSite();

        $this->service()->fetchProducts([$empty, $filled]);

        $this->assertSame('<table>舊的</table>', $empty->fresh()->size_chart);
        // 說明本來就有內容的商品不另外抓尺寸表
        $this->assertCount(1, Http::recorded(fn (Request $request) => str_ends_with($request->url(), 'sizeAndTryOnH5.html')));
    }

    public function test_requests_for_one_product_are_spaced_out(): void
    {
        Config::set('app.crawler.delay', ['min' => 50000, 'max' => 50000]);
        $product = $this->createGuProduct();
        $this->fakeOfficialSite();
        $sentAt = [];
        Event::listen(RequestSending::class, function () use (&$sentAt) {
            $sentAt[] = microtime(true);
        });

        $this->service()->fetchProducts([$product]);

        // 詳細資料、三個片段、最後清快取共五個請求；三個片段前各等一次，抓完再等一次，所以每兩個請求之間都隔開
        $this->assertCount(5, $sentAt);
        foreach (range(1, 4) as $i) {
            $this->assertGreaterThanOrEqual(0.05, $sentAt[$i] - $sentAt[$i - 1], "第 {$i} 個與下一個請求之間沒有間隔");
        }
    }

    public function test_new_products_come_before_listing_changes_and_the_rest_are_capped(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(3));

        $oldNeverFetched = $this->createGuProduct('u001', createdDaysAgo: 400);
        $oldDue = $this->createGuProduct('u002', createdDaysAgo: 400, detail: ['next_fetch_at' => now()->subDay()]);
        $oldNotDue = $this->createGuProduct('u003', createdDaysAgo: 400, detail: ['next_fetch_at' => now()->addDays(10)]);
        $changed = $this->createGuProduct('u004', createdDaysAgo: 400, detail: ['next_fetch_at' => now()->addDays(10), 'stale_since' => now()->subHours(5)]);
        $brandNew = $this->createGuProduct('u005', createdDaysAgo: 0);
        $followUp = $this->createGuProduct('u006', createdDaysAgo: 7, detail: ['next_fetch_at' => now()->subHour()]);
        $newNotDue = $this->createGuProduct('u007', createdDaysAgo: 5, detail: ['next_fetch_at' => now()->addDays(2)]);
        $this->createGuProduct('u008', createdDaysAgo: 0, attributes: ['stockout_at' => now()]);
        $this->createGuProduct('u009', createdDaysAgo: 0, attributes: ['brand' => 'UNIQLO']);

        $codes = fn ($products) => $products->pluck('product_code')->all();

        $this->assertSame(['u005', 'u006', 'u004', 'u001', 'u002'], $codes($this->service()->dueProducts('GU', 10, 10)));
        $this->assertSame(['u005', 'u006', 'u001'], $codes($this->service()->dueProducts('GU', 2, 1)));
    }

    public function test_follow_ups_follow_the_configured_days_then_rotate(): void
    {
        $product = $this->createGuProduct(createdDaysAgo: 0);
        $listedAt = $product->created_at;
        $service = $this->service();

        $this->assertEquals($listedAt->copy()->addDays(3), $service->nextFetchAt($product, $listedAt->copy()->addHour()));
        $this->assertEquals($listedAt->copy()->addDays(7), $service->nextFetchAt($product, $listedAt->copy()->addDays(3)));
        $this->assertEquals($listedAt->copy()->addDays(14), $service->nextFetchAt($product, $listedAt->copy()->addDays(8)));

        $fetchedAt = $listedAt->copy()->addDays(14);
        $next = $service->nextFetchAt($product, $fetchedAt);
        $this->assertGreaterThanOrEqual(30, $fetchedAt->diffInDays($next));
        $this->assertLessThanOrEqual(60, $fetchedAt->diffInDays($next));
    }

    public function test_a_product_without_a_listing_time_goes_straight_to_rotation(): void
    {
        $product = $this->createGuProduct();
        $product->created_at = null;
        $fetchedAt = now();

        foreach (range(1, 5) as $ignored) {
            $days = $fetchedAt->diffInDays($this->service()->nextFetchAt($product, $fetchedAt));
            $this->assertGreaterThanOrEqual(30, $days);
            $this->assertLessThanOrEqual(60, $days);
        }
    }

    public function test_purges_are_sent_in_batches(): void
    {
        $products = collect(range(1, 31))->map(fn ($i) => $this->createGuProduct(sprintf('u%03d', $i)));
        $this->fakeOfficialSite();

        $this->service()->fetchProducts($products);

        $purges = Http::recorded(fn (Request $request) => $request->url() === self::PURGE_API)
            ->map(fn ($pair) => count($pair[0]->data()['files']))
            ->values()
            ->all();
        $this->assertSame([30, 1], $purges);
    }

    /**
     * Http::fake 再呼叫一次只會往後追加，先註冊的規則仍然優先，所以回應內容放在屬性裡，測試中途改屬性。
     */
    private function fakeOfficialSite(string $instruction = self::NEW_DESCRIPTION, int $fragmentStatus = 200): void
    {
        $this->instruction = $instruction;
        $this->fragmentStatus = $fragmentStatus;

        if ($this->faked) {
            return;
        }

        $this->faked = true;
        $headers = ['ETag' => '"abc"', 'Last-Modified' => 'Tue, 03 Nov 2026 03:16:59 GMT'];
        $fragment = fn (string $body, string $file) => match (true) {
            in_array($file, $this->missing, true) => Http::response('', 404),
            $this->fragmentStatus === 304 => Http::response('', 304, $headers),
            default => Http::response($body, 200, $headers),
        };

        Http::fake([
            self::DETAIL_API.'*' => fn () => Http::response($this->stub('hmall-product-detail-gu.json')),
            '*/instructionH5.html' => fn () => $fragment($this->instruction, 'instructionH5.html'),
            '*/features.html' => fn () => $fragment($this->features ?? file_get_contents(base_path('tests/stubs/hmall-product-features-gu.html')), 'features.html'),
            '*/materialCare.html' => fn () => $fragment($this->materialCare ?? file_get_contents(base_path('tests/stubs/hmall-product-material-care-gu.html')), 'materialCare.html'),
            '*/sizeAndTryOnH5.html' => fn () => Http::response($this->sizeChart),
            self::PURGE_API => Http::response(['success' => true]),
        ]);
    }

    private function createGuProduct(
        string $productCode = 'u0000000053291',
        int $createdDaysAgo = 100,
        array $attributes = [],
        ?array $detail = null,
    ): HmallProduct {
        $product = HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'GU',
            'name' => "商品 {$productCode}",
            'code' => '360889',
            'product_code' => $productCode,
            'stock' => 'Y',
            'instruction' => self::OLD_DESCRIPTION,
            'size_chart' => '',
            'created_at' => now()->subDays($createdDaysAgo),
            'updated_at' => now()->subDays($createdDaysAgo),
        ], $attributes)));

        if ($detail !== null) {
            $product->detail()->create($detail + ['fetched_at' => now()->subDays(40)]);
        }

        return $product;
    }

    private function stub(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/stubs/{$name}")), true);
    }

    private function service(): HmallProductDetailService
    {
        return app(HmallProductDetailService::class);
    }
}
