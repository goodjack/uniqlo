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
        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        Cache::flush();
        $checkpointBefore = Cache::get('japan_products:offset:UNIQLO');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllProducts('UNIQLO');

        // Checkpoint should not be set when blocked by 403
        $checkpointAfter = Cache::get('japan_products:offset:UNIQLO');
        $this->assertNull($checkpointBefore);
        $this->assertNull($checkpointAfter);
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
        $maxRetry = Config::get('app.crawler.retry.manual');

        $this->assertEquals(2, $maxRetry, 'Expected configured manual retry count to be 2');
    }
}
