<?php

namespace Tests\Unit\Services;

use App\Models\HmallProduct;
use App\Services\HmallProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HmallProductDescriptionRefetchTest extends TestCase
{
    use RefreshDatabase;

    private const EMPTY_SHELL = '<div class="product-desc"></div>';

    private const ONLY_BOILERPLATE = '<div class="product-desc">商品材質<br>棉 100%</div>';

    private const REAL_DESCRIPTION = '<div class="product-desc">柔軟的棉質面料，四季都好穿。</div>';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('uniqlo.api.v3.description.tw', 'https://api.example.com/description/');
        Config::set('app.crawler.delay', ['min' => 0, 'max' => 0]);
        Config::set('app.crawler.retry', ['times' => 1, 'sleep_min' => 0, 'sleep_max' => 0]);
    }

    public function test_only_in_stock_products_with_an_empty_description_are_refetched_and_filled(): void
    {
        $empty = $this->createProduct('u001', self::EMPTY_SHELL);
        $boilerplate = $this->createProduct('u002', self::ONLY_BOILERPLATE);
        $filled = $this->createProduct('u003', self::REAL_DESCRIPTION);
        $stockedOut = $this->createProduct('u004', self::EMPTY_SHELL, ['stockout_at' => now()]);
        Http::fake(['*' => Http::response(self::REAL_DESCRIPTION)]);

        $this->assertTrue($this->service()->refetchEmptyHmallProductDescriptions('UNIQLO'));

        $this->assertSame(self::REAL_DESCRIPTION, $empty->fresh()->instruction);
        $this->assertSame(self::REAL_DESCRIPTION, $boilerplate->fresh()->instruction);
        $this->assertSame(self::EMPTY_SHELL, $stockedOut->fresh()->instruction);
        Http::assertSentCount(4);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'u003') || str_contains($request->url(), 'u004'));
    }

    public function test_a_still_empty_answer_leaves_the_saved_size_chart_and_updated_at_alone(): void
    {
        $product = $this->createProduct('u001', self::EMPTY_SHELL, ['size_chart' => '<table>尺寸</table>']);
        $updatedAt = $product->updated_at;
        $this->travel(1)->days();
        Http::fake(['*instructionH5*' => Http::response(self::EMPTY_SHELL), '*' => Http::response('')]);

        $this->service()->refetchEmptyHmallProductDescriptions('UNIQLO');

        $this->assertSame('<table>尺寸</table>', $product->fresh()->size_chart);
        $this->assertEquals($updatedAt, $product->fresh()->updated_at);
    }

    public function test_a_blank_size_chart_from_the_official_site_does_not_replace_the_saved_one(): void
    {
        $product = $this->createProduct('u001', self::EMPTY_SHELL, ['size_chart' => '<table>尺寸</table>']);
        Http::fake(['*instructionH5*' => Http::response(self::REAL_DESCRIPTION), '*' => Http::response('<div></div>')]);

        $this->service()->refetchEmptyHmallProductDescriptions('UNIQLO');

        $this->assertSame(self::REAL_DESCRIPTION, $product->fresh()->instruction);
        $this->assertSame('<table>尺寸</table>', $product->fresh()->size_chart);
    }

    public function test_filling_a_description_does_not_touch_updated_at(): void
    {
        $product = $this->createProduct('u001', self::EMPTY_SHELL);
        $updatedAt = $product->updated_at;
        $this->travel(1)->days();
        Http::fake(['*' => Http::response(self::REAL_DESCRIPTION)]);

        $this->service()->refetchEmptyHmallProductDescriptions('UNIQLO');

        $this->assertSame(self::REAL_DESCRIPTION, $product->fresh()->instruction);
        $this->assertEquals($updatedAt, $product->fresh()->updated_at);
    }

    public function test_each_run_checks_at_most_the_limit_and_the_next_run_continues_then_wraps_around(): void
    {
        foreach (['u001', 'u002', 'u003'] as $code) {
            $this->createProduct($code, self::EMPTY_SHELL);
        }
        Http::fake(['*' => Http::response(self::EMPTY_SHELL)]);

        $checkedPerRun = [];
        foreach (range(1, 3) as $run) {
            $before = Http::recorded()->count();
            $this->service()->refetchEmptyHmallProductDescriptions('UNIQLO', 2);
            $checkedPerRun[] = array_map(
                fn ($pair) => explode('/', $pair[0]->url())[4],
                array_values(array_filter(
                    array_slice(Http::recorded()->all(), $before),
                    fn ($pair) => str_contains($pair[0]->url(), 'instructionH5')
                ))
            );
        }

        $this->assertSame([['u003', 'u002'], ['u001'], ['u003', 'u002']], $checkedPerRun);
    }

    public function test_a_missing_page_is_skipped_and_the_rest_still_run(): void
    {
        $next = $this->createProduct('u001', self::EMPTY_SHELL);
        $missing = $this->createProduct('u002', self::EMPTY_SHELL);
        Http::fake([
            '*/u002/*' => Http::response('not found', 404),
            '*' => Http::response(self::REAL_DESCRIPTION),
        ]);

        $this->assertTrue($this->service()->refetchEmptyHmallProductDescriptions('UNIQLO'));

        $this->assertSame(self::EMPTY_SHELL, $missing->fresh()->instruction);
        $this->assertSame(self::REAL_DESCRIPTION, $next->fresh()->instruction);
    }

    public function test_a_description_that_cannot_be_saved_is_logged_and_the_rest_still_run(): void
    {
        $next = $this->createProduct('u001', self::EMPTY_SHELL);
        $tooLong = $this->createProduct('u002', self::EMPTY_SHELL);
        Http::fake([
            '*/u002/zh_TW/instructionH5.html' => Http::response('<div class="product-desc">'.str_repeat('長', 30000).'</div>'),
            '*' => Http::response(self::REAL_DESCRIPTION),
        ]);
        Log::spy();

        $this->assertTrue($this->service()->refetchEmptyHmallProductDescriptions('UNIQLO'));

        $this->assertSame(self::EMPTY_SHELL, $tooLong->fresh()->instruction);
        $this->assertSame(self::REAL_DESCRIPTION, $next->fresh()->instruction);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'saveHmallProductDescriptions failed')->once();
    }

    public function test_being_blocked_stops_the_run(): void
    {
        $untouched = $this->createProduct('u001', self::EMPTY_SHELL);
        $this->createProduct('u002', self::EMPTY_SHELL);
        Http::fake(['*/u002/*' => Http::response('', 403), '*' => Http::response(self::REAL_DESCRIPTION)]);

        $this->assertFalse($this->service()->refetchEmptyHmallProductDescriptions('UNIQLO'));

        $this->assertSame(self::EMPTY_SHELL, $untouched->fresh()->instruction);
    }

    private function service(): HmallProductService
    {
        return app(HmallProductService::class);
    }

    private function createProduct(string $productCode, string $instruction, array $attributes = []): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => "商品 {$productCode}",
            'code' => substr($productCode, 1),
            'product_code' => $productCode,
            'stock' => 'Y',
            'instruction' => $instruction,
            'size_chart' => '',
        ], $attributes)));
    }
}
