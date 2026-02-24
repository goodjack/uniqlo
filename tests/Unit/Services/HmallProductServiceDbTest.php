<?php

namespace Tests\Unit\Services;

use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\HmallProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Tests\TestCase;

class HmallProductServiceDbTest extends TestCase
{
    use RefreshDatabase;

    private HmallProductService $service;
    private HmallProductRepository $mockHmallRepository;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);

        Config::set('uniqlo.api.v3.description.tw', 'https://api.example.com/description/');

        $this->mockHmallRepository = $this->createMock(HmallProductRepository::class);
        $this->mockHmallRepository->method('updateProductDescriptionsFromV3')->willReturn(true);

        $mockProductRepository = $this->createMock(ProductRepository::class);

        $this->service = new HmallProductService($this->mockHmallRepository, $mockProductRepository);
    }

    public function test_detail_counter_only_incremented_on_success()
    {
        // Insert 2 HmallProducts: PCODE001 (will succeed), PCODE002 (will fail)
        HmallProduct::unguarded(function () {
            HmallProduct::create(['product_code' => 'PCODE001', 'brand' => 'UNIQLO']);
            HmallProduct::create(['product_code' => 'PCODE002', 'brand' => 'UNIQLO']);
        });

        Http::fake([
            // PCODE001 description endpoints → 200 success
            'https://api.example.com/description/PCODE001/*' => Http::response('<html>instruction</html>', 200),
            // PCODE002 description endpoints → 500 failure (retry exhausted)
            'https://api.example.com/description/PCODE002/*' => Http::response([], 500),
        ]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProductDescriptions('UNIQLO');

        // detailCounter should be 1: PCODE001 succeeded, PCODE002 failed and was skipped
        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('detailCounter');
        $property->setAccessible(true);
        $detailCounter = $property->getValue($this->service);

        $this->assertEquals(1, $detailCounter, 'Failed items must not increment detailCounter');
    }
}
