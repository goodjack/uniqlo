<?php

namespace Tests\Unit;

use App\Product;
use App\MultiBuyHistory;
use App\Repositories\ProductRepository;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        $productOne = factory(Product::class)->create();
        $productOneMultiBuy = $productOne->multiBuys()->save(factory(MultiBuyHistory::class)->create());

        $productTwo = factory(Product::class)->create();
        $productTwoMultiBuy = $productTwo->multiBuys()->save(factory(MultiBuyHistory::class)->create([
            'created_at' => \Carbon\Carbon::now()->subDay()
        ]));

        $this->productRepository->setMultiBuyProducts();

        $productOne = Product::find($productOne->id);
        $productTwo = Product::find($productTwo->id);

        $this->assertEquals($productOne->multi_buy, $productOneMultiBuy->multi_buy);
        $this->assertEquals($productTwo->multi_buy, null);
    }
}
