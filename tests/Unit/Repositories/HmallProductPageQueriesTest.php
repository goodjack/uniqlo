<?php

namespace Tests\Unit\Repositories;

use App\Models\HmallProduct;
use App\Models\Product;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HmallProductPageQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_related_products_skip_the_name_scan_on_the_second_visit(): void
    {
        $product = $this->createProduct(['name' => '圓領T恤', 'code' => '450001']);
        $shortSleeve = $this->createProduct(['name' => '圓領T恤 短袖', 'code' => '450002', 'product_code' => 'u450002']);

        $repository = app(HmallProductRepository::class);
        // 短袖跟這件不同 code，只能靠名稱比對找到
        $this->assertSame([$shortSleeve->id], $repository->getRelatedHmallProducts($product)->pluck('id')->all());

        $this->createProduct(['name' => '圓領T恤 長袖', 'code' => '450003', 'product_code' => 'u450003']);

        // 第二次沿用第一次的候選，所以同樣靠名稱才找得到的長袖不會出現
        $this->assertSame([$shortSleeve->id], $repository->getRelatedHmallProducts($product)->pluck('id')->all());
    }

    public function test_cached_related_products_are_still_ranked_by_the_latest_stock_and_price(): void
    {
        $product = $this->createProduct(['name' => '圓領T恤', 'code' => '450001']);
        $cheap = $this->createProduct(['name' => '圓領T恤 短袖', 'code' => '450002', 'product_code' => 'u450002', 'min_price' => 390]);
        $pricey = $this->createProduct(['name' => '圓領T恤 長袖', 'code' => '450003', 'product_code' => 'u450003', 'min_price' => 590]);

        $repository = app(HmallProductRepository::class);
        $this->assertSame([$cheap->id, $pricey->id], $repository->getRelatedHmallProducts($product)->pluck('id')->all());

        $pricey->forceFill(['min_price' => 290])->save();
        $this->assertSame([$pricey->id, $cheap->id], $repository->getRelatedHmallProducts($product)->pluck('id')->all());

        $pricey->forceFill(['stockout_at' => now()])->save();
        $this->assertSame([$cheap->id, $pricey->id], $repository->getRelatedHmallProducts($product)->pluck('id')->all());
    }

    public function test_the_legacy_product_lookup_by_name_is_remembered_even_when_nothing_matches(): void
    {
        $found = $this->createProduct(['name' => '圓領T恤', 'code' => '450001']);
        $missing = $this->createProduct(['name' => '舊系統沒有的商品', 'code' => '450002', 'product_code' => 'u450002']);
        Product::unguarded(fn () => Product::create(['id' => '4199990001', 'name' => '男裝 圓領T恤', 'price' => 390]));

        $repository = app(ProductRepository::class);
        $this->assertSame(['4199990001'], $repository->getRelatedProductsForHmallProduct($found)->pluck('id')->all());
        $this->assertEmpty($repository->getRelatedProductsForHmallProduct($missing));

        // 第一次找不到也要記住：之後舊系統冒出能用名稱比對到的商品，也不再重掃整張表
        Product::unguarded(fn () => Product::create(['id' => '4199990002', 'name' => '男裝 舊系統沒有的商品', 'price' => 390]));
        $this->assertEmpty($repository->getRelatedProductsForHmallProduct($missing));
    }

    public function test_the_latest_style_hints_come_first(): void
    {
        $product = $this->createProduct(['code' => '450001']);
        foreach ([3, 1, 2] as $id) {
            DB::table('style_hints')->insert(['id' => $id, 'outfit_id' => "outfit-{$id}", 'country' => 'tw']);
            DB::table('style_hint_items')->insert(['style_hint_id' => $id, 'code' => '450001', 'original_product_id' => "p{$id}"]);
        }

        $styleHints = app(HmallProductRepository::class)->getStyleHints($product, 2);

        $this->assertSame([3, 2], $styleHints->pluck('id')->all());
    }

    public function test_official_styles_are_found_by_the_product_id(): void
    {
        $product = $this->createProduct(['code' => '450001']);
        DB::table('styles')->insert(['id' => 'style-1', 'dpt_id' => 'd1', 'image_path' => '/1.jpg', 'created_at' => now()]);
        $product->styles()->sync(['style-1']);

        $this->assertSame(['style-1'], $product->fresh()->styles->pluck('id')->all());
        $this->assertSame('bigint', Schema::getColumnType('hmall_product_style', 'hmall_product_id'));
    }

    private function createProduct(array $attributes): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => '測試商品',
            'product_code' => 'u450001',
            'gender' => '男裝',
            'sex' => '男裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 490,
            'lowest_record_price' => 490,
            'highest_record_price' => 490,
        ], $attributes)));
    }
}
