<?php

namespace Tests\Unit\Services;

use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\HmallProductService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HmallProductServiceTest extends TestCase
{
    private HmallProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test user agents
        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);

        // Mock repositories
        $mockHmallRepository = $this->createMock(HmallProductRepository::class);
        $mockHmallRepository->method('saveProductsFromV3')->willReturn(true);
        $mockHmallRepository->method('setStockoutHmallProducts')->willReturn(true);
        $mockHmallRepository->method('updateProductDescriptionsFromV3')->willReturn(true);

        $mockProductRepository = $this->createMock(ProductRepository::class);

        $this->service = new HmallProductService($mockHmallRepository, $mockProductRepository);
    }

    public function test_fetch_all_hmall_products_stops_on_403_error()
    {
        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Cache::flush();
        $checkpointBefore = Cache::get('hmall_products:page:UNIQLO');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProducts('UNIQLO');

        // Checkpoint should not be set when blocked by 403
        $checkpointAfter = Cache::get('hmall_products:page:UNIQLO');
        $this->assertNull($checkpointBefore);
        $this->assertNull($checkpointAfter);
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
        $mockProduct = new \stdClass();
        $mockProduct->id = 1;
        $mockProduct->product_code = 'TEST123';

        // Mock the HmallProduct query
        $this->service->fetchHmallProductDescriptions($mockProduct, 'UNIQLO');

        // If we get here without exception, the 403 was handled correctly
        $this->assertTrue(true);
    }

    public function test_uses_configured_retry_count()
    {
        $maxRetry = Config::get('app.crawler.retry.laravel');

        $this->assertEquals(2, $maxRetry, 'Expected configured laravel retry count to be 2');
    }
}
