<?php

namespace Tests\Unit;

use App\Models\MultiBuyHistory;
use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected $productRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->productRepository = $this->app->make(ProductRepository::class);
    }

    /**
     * A MULTI_BUY test.
     *
     * @return void
     */
    public function testMultiBuy()
    {
        $productOne = Product::factory()->create();
        $productOneMultiBuy = $productOne->multiBuys()->save(MultiBuyHistory::factory()->create());

        $productTwo = Product::factory()->create();
        $productTwoMultiBuy = $productTwo->multiBuys()->save(MultiBuyHistory::factory()->create([
            'created_at' => \Carbon\Carbon::now()->subDay()
        ]));

        $this->productRepository->setMultiBuyProducts();

        $productOne = Product::find($productOne->id);
        $productTwo = Product::find($productTwo->id);

        $this->assertEquals($productOne->multi_buy, $productOneMultiBuy->multi_buy);
        $this->assertEquals($productTwo->multi_buy, null);
    }
}
