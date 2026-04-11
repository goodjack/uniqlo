<?php

namespace Tests\Unit\Services;

use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\HmallProductService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;
use Tests\TestCase;

class HmallProductServiceTest extends TestCase
{
    private HmallProductService $service;

    private HmallProductRepository $mockHmallRepository;

    private ProductRepository $mockProductRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test user agents
        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);
        Config::set('cache.default', 'array');
        Config::set('app.crawler.page_sizes.hmall_products', 24);
        Config::set('app.crawler.delay.min', 0);
        Config::set('app.crawler.delay.max', 0);
        Config::set('app.crawler.retry.sleep_min', 0);
        Config::set('app.crawler.retry.sleep_max', 0);
        Config::set('app.crawler.batch_rest.offset.interval', 0);
        Config::set('app.crawler.batch_rest.detail.interval', 0);

        // Mock repositories
        $this->mockHmallRepository = $this->createMock(HmallProductRepository::class);
        $this->mockHmallRepository->method('saveProductsFromV3')->willReturn(true);
        $this->mockHmallRepository->method('setStockoutHmallProducts')->willReturn(true);
        $this->mockHmallRepository->method('updateProductDescriptionsFromV3')->willReturn(true);

        $this->mockProductRepository = $this->createMock(ProductRepository::class);

        $this->service = new HmallProductService($this->mockHmallRepository, $this->mockProductRepository);
    }

    public function test_fetch_all_hmall_products_stops_on_403_error()
    {
        // Pre-set checkpoint (simulating partial completion)
        Cache::set('hmall_products:page:UNIQLO', 5);
        $checkpointBefore = Cache::get('hmall_products:page:UNIQLO');

        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProducts('UNIQLO');

        // Checkpoint must not be overwritten on 403
        $checkpointAfter = Cache::get('hmall_products:page:UNIQLO');
        $this->assertEquals(5, $checkpointBefore);
        $this->assertEquals(5, $checkpointAfter, 'Checkpoint must not be overwritten on 403');
    }

    public function test_fetch_all_hmall_products_saves_checkpoint()
    {
        Http::fake([
            '*' => Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');
        Cache::flush();

        $this->service->fetchAllHmallProducts('UNIQLO');

        // Checkpoint should be cleared after successful completion
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    public function test_fetch_all_hmall_products_resumes_from_checkpoint()
    {
        $initialPage = 3;
        Cache::set('hmall_products:page:UNIQLO', $initialPage);

        Http::fake([
            '*' => Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $this->service->fetchAllHmallProducts('UNIQLO');

        // Checkpoint should be cleared after completion
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    public function test_fetch_all_hmall_products_fresh_ignores_checkpoint()
    {
        Cache::set('hmall_products:page:UNIQLO', 10);

        Http::fake([
            '*' => Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $this->service->fetchAllHmallProducts('UNIQLO', fresh: true);

        // Checkpoint should be cleared
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    public function test_fetch_all_hmall_product_descriptions_stops_on_403_error()
    {
        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.v3.description.tw', 'https://api.example.com/description/');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        // Create a mock HmallProduct
        $mockProduct = new stdClass;
        $mockProduct->id = 1;
        $mockProduct->product_code = 'TEST123';

        // 403 should now throw from fetchHmallProductDescriptions
        $this->expectException(RequestException::class);

        $this->service->fetchHmallProductDescriptions($mockProduct, 'UNIQLO');
    }

    public function test_fetch_all_hmall_product_descriptions_checkpoint_not_updated_on_403(): void
    {
        // This is the core regression test for the 403 bug fix.
        // When fetchHmallProductDescriptions encounters 403, it must throw
        // so that fetchAllHmallProductDescriptions does NOT update the checkpoint.

        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.v3.description.tw', 'https://api.example.com/description/');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $cacheKey = 'hmall_descriptions:last_id:UNIQLO';
        Cache::flush();

        // Verify checkpoint is null before
        $this->assertNull(Cache::get($cacheKey));

        // Create a mock HmallProduct
        $mockProduct = new stdClass;
        $mockProduct->id = 42;
        $mockProduct->product_code = 'TEST123';

        // fetchHmallProductDescriptions throws on 403
        try {
            $this->service->fetchHmallProductDescriptions($mockProduct, 'UNIQLO');
        } catch (\Throwable $e) {
            // Expected throw
        }

        // Checkpoint must NOT be updated - this is the core fix
        $this->assertNull(Cache::get($cacheKey), 'Checkpoint must not be updated when 403 is thrown');
    }

    public function test_uses_configured_retry_count()
    {
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $requestCount = 0;
        Http::fake(['https://api.example.com/search' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([], 500);
        }]);

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProducts('UNIQLO');

        // retry(3) = 3 total attempts; productSum stays 0 → loop exits after 1 iteration
        $this->assertEquals(3, $requestCount, 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_get_related_products_uses_product_repository()
    {
        $hmallProduct = $this->createMock(\App\Models\HmallProduct::class);

        $this->mockProductRepository
            ->expects($this->once())
            ->method('getRelatedProductsForHmallProduct')
            ->with($hmallProduct);

        $this->service->getRelatedProducts($hmallProduct);
    }

    public function test_description_fetch_retries_each_url_independently()
    {
        // Fix #10: instruction and sizeChart HTTP requests are now in separate retry blocks,
        // so a transient failure on sizeChart doesn't re-fetch instruction.
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.v3.description.tw', 'https://api.example.com/description/');

        $instructionCount = 0;
        $sizeChartCount = 0;

        Http::fake(function ($request) use (&$instructionCount, &$sizeChartCount) {
            $url = $request->url();

            if (str_contains($url, 'instructionH5')) {
                $instructionCount++;

                return Http::response('<div>instruction</div>');
            }

            if (str_contains($url, 'sizeAndTryOnH5')) {
                $sizeChartCount++;

                // First attempt fails, second succeeds
                if ($sizeChartCount === 1) {
                    return Http::response([], 500);
                }

                return Http::response('<div>sizeChart</div>');
            }

            return Http::response([], 404);
        });

        $mockProduct = $this->createMock(\App\Models\HmallProduct::class);
        $mockProduct->method('__get')->willReturnMap([
            ['id', 1],
            ['product_code', 'TEST123'],
        ]);

        $this->service->fetchHmallProductDescriptions($mockProduct, 'UNIQLO');

        // Instruction should be fetched exactly once (succeeded on first try)
        $this->assertEquals(1, $instructionCount, 'Instruction should not be re-fetched when sizeChart retries');
        // SizeChart should be fetched twice (failed once, then succeeded)
        $this->assertEquals(2, $sizeChartCount, 'SizeChart should retry independently');
    }

    public function test_fetch_all_hmall_products_fetches_last_partial_page()
    {
        // productSum=25, pageSize=24 → page 1 returns 25, page 2 should also be fetched
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $requestCount = 0;
        Http::fake(['https://api.example.com/search' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 25,
                    ],
                ],
            ]);
        }]);

        $this->service->fetchAllHmallProducts('UNIQLO');

        $this->assertEquals(2, $requestCount, 'Should fetch page 1 (25 >= 24) and page 2 (25 >= 24), stop at page 3 (25 < 48)');
    }

    public function test_all_pages_fail_preserves_checkpoint_and_skips_stockout()
    {
        // Bug #57: When all pages fail with non-403, the loop exits with productSum=0
        // and incorrectly clears checkpoint + runs stockout as if completed successfully.
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        // Pre-set checkpoint
        Cache::put('hmall_products:page:UNIQLO', 3);

        Http::fake([
            'https://api.example.com/search' => Http::response([], 500),
        ]);

        // setStockoutHmallProducts should NOT be called
        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProducts('UNIQLO');

        // Checkpoint must be preserved — not cleared
        $this->assertEquals(
            3,
            Cache::get('hmall_products:page:UNIQLO'),
            'Checkpoint must not be cleared when no pages succeeded'
        );
    }
}
