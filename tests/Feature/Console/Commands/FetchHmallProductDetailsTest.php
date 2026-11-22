<?php

namespace Tests\Feature\Console\Commands;

use App\Events\AppTaskFailed;
use App\Models\HmallProduct;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchHmallProductDetailsTest extends TestCase
{
    use RefreshDatabase;

    private const DETAIL_API = 'https://api.example.com/detail';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('gu.api.v3.detail.tw', self::DETAIL_API);
        Config::set('uniqlo.api.v3.detail.tw', self::DETAIL_API);
        Config::set('app.crawler.delay', ['min' => 0, 'max' => 0]);
        Config::set('app.crawler.retry', ['times' => 1, 'sleep_min' => 0, 'sleep_max' => 0]);
        Event::fake([AppTaskFailed::class]);
    }

    public function test_named_products_are_fetched_regardless_of_the_schedule(): void
    {
        $this->createProduct('GU', 'u001', ['next_fetch_at' => now()->addDays(30)]);
        $this->createProduct('GU', 'u002', ['next_fetch_at' => now()->addDays(30)]);
        Http::fake([self::DETAIL_API.'*' => Http::response(['success' => false, 'resp' => []])]);

        $this->artisan('hmall-product-detail:fetch', ['brand' => 'gu', '--product' => ['u002']])
            ->assertExitCode(Command::SUCCESS);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'productCode=u002'));
    }

    public function test_without_a_brand_both_brands_run_and_limits_cap_each_one(): void
    {
        $this->createProduct('UNIQLO', 'u001');
        $this->createProduct('UNIQLO', 'u002');
        $this->createProduct('GU', 'u003');
        // 上架很久、已經到期該輪替的那件：輪替組上限 0，這一輪不抓
        $this->createProduct('GU', 'u004', ['fetched_at' => now()->subDays(40), 'next_fetch_at' => now()->subDay()])
            ->forceFill(['created_at' => now()->subDays(400)])
            ->save();
        Http::fake([self::DETAIL_API.'*' => Http::response(['success' => false, 'resp' => []])]);

        $this->artisan('hmall-product-detail:fetch', ['--priority-limit' => 1, '--rotation-limit' => 0])
            ->assertExitCode(Command::SUCCESS);

        $sentCodes = Http::recorded()
            ->map(fn (array $pair) => (string) $pair[0]['productCode'])
            ->sort()
            ->values()
            ->all();
        // UNIQLO 有兩件但上限一件；GU 那件也要抓到，到期輪替的不抓
        $this->assertCount(2, $sentCodes);
        $this->assertSame('u003', end($sentCodes));
        $this->assertContains($sentCodes[0], ['u001', 'u002']);
    }

    public function test_being_blocked_fails_the_command_reports_it_and_skips_the_other_brand(): void
    {
        $this->createProduct('UNIQLO', 'u001');
        $this->createProduct('GU', 'u002');
        Http::fake([self::DETAIL_API.'*' => Http::response('', 403)]);

        $this->artisan('hmall-product-detail:fetch')->assertExitCode(Command::FAILURE);

        Http::assertSentCount(1);
        Event::assertDispatched(AppTaskFailed::class);
    }

    public function test_one_brand_failing_over_and_over_does_not_hold_up_the_other(): void
    {
        Config::set('app.crawler.product_details.max_consecutive_failures', 1);
        $this->createProduct('UNIQLO', 'u001');
        $this->createProduct('GU', 'u002');
        Http::fake([
            self::DETAIL_API.'?productCode=u001*' => Http::response('', 500),
            self::DETAIL_API.'*' => Http::response(['success' => false, 'resp' => []]),
        ]);

        $this->artisan('hmall-product-detail:fetch')->assertExitCode(Command::FAILURE);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'productCode=u002'));
        Event::assertDispatched(AppTaskFailed::class);
    }

    private function createProduct(string $brand, string $productCode, ?array $detail = null): HmallProduct
    {
        $product = HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => $brand,
            'name' => "商品 {$productCode}",
            'code' => substr($productCode, 1),
            'product_code' => $productCode,
            'stock' => 'Y',
        ]));

        if ($detail !== null) {
            $product->detail()->create($detail);
        }

        return $product;
    }
}
