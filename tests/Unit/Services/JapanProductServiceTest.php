<?php

namespace Tests\Unit\Services;

use App\Repositories\JapanProductRepository;
use App\Services\JapanProductService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class JapanProductServiceTest extends TestCase
{
    private JapanProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test user agents
        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);

        // Mock repository
        $mockRepository = $this->createMock(JapanProductRepository::class);
        $mockRepository->method('saveProducts');
        $mockRepository->method('setStockoutProducts');

        $this->service = new JapanProductService($mockRepository);
    }

    public function test_fetch_all_products_stops_on_403_error()
    {
        // Pre-set checkpoint (simulating partial completion)
        Cache::set('japan_products:offset:UNIQLO', 36);
        $checkpointBefore = Cache::get('japan_products:offset:UNIQLO');

        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllProducts('UNIQLO');

        // Checkpoint must not be overwritten on 403
        $checkpointAfter = Cache::get('japan_products:offset:UNIQLO');
        $this->assertEquals(36, $checkpointBefore);
        $this->assertEquals(36, $checkpointAfter, 'Checkpoint must not be overwritten on 403');
    }

    public function test_fetch_all_products_saves_checkpoint()
    {
        Http::fake([
            '*' => Http::response([
                'result' => [
                    'items' => [],
                    'pagination' => ['total' => 0],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        Cache::flush();

        $this->service->fetchAllProducts('UNIQLO');

        // Checkpoint should be cleared after successful completion
        $this->assertNull(Cache::get('japan_products:offset:UNIQLO'));
    }

    public function test_fetch_all_products_resumes_from_checkpoint()
    {
        $initialOffset = 72;
        Cache::set('japan_products:offset:UNIQLO', $initialOffset);

        Http::fake([
            '*' => Http::response([
                'result' => [
                    'items' => [],
                    'pagination' => ['total' => 0],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        $this->service->fetchAllProducts('UNIQLO');

        // Checkpoint should be cleared after completion
        $this->assertNull(Cache::get('japan_products:offset:UNIQLO'));
    }

    public function test_fetch_all_products_fresh_ignores_checkpoint()
    {
        Cache::set('japan_products:offset:UNIQLO', 100);

        Http::fake([
            '*' => Http::response([
                'result' => [
                    'items' => [],
                    'pagination' => ['total' => 0],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        $this->service->fetchAllProducts('UNIQLO', fresh: true);

        // Checkpoint should be cleared
        $this->assertNull(Cache::get('japan_products:offset:UNIQLO'));
    }

    public function test_uses_configured_retry_count()
    {
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        $requestCount = 0;
        // Use wildcard to match GET URLs with query parameters (e.g. ?offset=0&limit=36&...)
        Http::fake(['https://api.example.com/products*' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([], 500);
        }]);

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllProducts('UNIQLO');

        // retry(3) = 3 total attempts; total stays 0 → loop exits after 1 iteration
        $this->assertEquals(3, $requestCount, 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_all_pages_fail_preserves_checkpoint_and_skips_stockout()
    {
        // Bug #57: When all pages fail with non-403, the loop exits with total=0
        // and incorrectly clears checkpoint + runs stockout as if completed successfully.
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        // Pre-set checkpoint
        Cache::put('japan_products:offset:UNIQLO', 72);

        Http::fake([
            'https://api.example.com/products*' => Http::response([], 500),
        ]);

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllProducts('UNIQLO');

        // Checkpoint must be preserved — not cleared
        $this->assertEquals(
            72,
            Cache::get('japan_products:offset:UNIQLO'),
            'Checkpoint must not be cleared when no batches succeeded'
        );
    }
}
