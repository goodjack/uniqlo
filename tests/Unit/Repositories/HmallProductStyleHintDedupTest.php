<?php

namespace Tests\Unit\Repositories;

use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HmallProductStyleHintDedupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_outfit_shared_to_several_countries_shows_once_preferring_taiwan(): void
    {
        $product = $this->createProduct('450001');
        $this->seedOutfit(1, 'tw', '900', ['450001']);
        $this->seedOutfit(2, 'jp', '900', ['450001']);
        $this->seedOutfit(3, 'us', '900', ['450001']);
        $this->seedOutfit(4, 'us', '901', ['450001']);
        $this->seedOutfit(5, 'jp', '901', ['450001']);

        $repository = app(HmallProductRepository::class);

        $this->assertSame([5, 1], $repository->getStyleHints($product, 12)->pluck('id')->all());
        $this->assertSame(2, $repository->getStyleHintCount($product));
    }

    public function test_an_outfit_tagging_two_colors_of_the_product_shows_once(): void
    {
        $product = $this->createProduct('450001');
        $this->seedOutfit(1, 'tw', '900', ['450001'], colors: ['03', '09']);

        $repository = app(HmallProductRepository::class);

        $this->assertSame([1], $repository->getStyleHints($product, 12)->pluck('id')->all());
        $this->assertSame(1, $repository->getStyleHintCount($product));
    }

    public function test_outfits_without_a_source_outfit_id_are_each_their_own(): void
    {
        $product = $this->createProduct('450001');
        $this->seedOutfit(1, 'tw', null, ['450001']);
        $this->seedOutfit(2, 'jp', null, ['450001']);

        $this->assertSame([2, 1], app(HmallProductRepository::class)->getStyleHints($product, 12)->pluck('id')->all());
    }

    public function test_a_country_copy_tagging_only_this_product_still_shows_when_the_taiwan_copy_does_not(): void
    {
        $product = $this->createProduct('450002');
        $this->seedOutfit(1, 'tw', '900', ['450001']);
        $this->seedOutfit(2, 'jp', '900', ['450001', '450002']);

        $this->assertSame([2], app(HmallProductRepository::class)->getStyleHints($product, 12)->pluck('id')->all());
    }

    public function test_commonly_styled_products_count_each_outfit_once(): void
    {
        $product = $this->createProduct('450001');
        $sharedOnce = $this->createProduct('450002');
        $sharedTwice = $this->createProduct('450003');
        $this->seedOutfit(1, 'tw', '900', ['450001', '450002'], colors: ['03', '09']);
        $this->seedOutfit(2, 'jp', '900', ['450001', '450002'], colors: ['03', '09']);
        $this->seedOutfit(3, 'tw', '901', ['450001', '450003']);
        $this->seedOutfit(4, 'tw', '902', ['450001', '450003']);

        $products = app(HmallProductRepository::class)->getCommonlyStyledHmallProducts($product);

        $this->assertSame([$sharedTwice->id, $sharedOnce->id], $products->pluck('id')->all());
        $this->assertSame([2, 1], $products->pluck('count')->map(fn ($count) => (int) $count)->all());
    }

    public function test_top_wearing_ranks_by_distinct_outfits_over_the_threshold(): void
    {
        $inflated = $this->createProduct('450001');
        $popular = $this->createProduct('450002');
        $id = 0;

        // 70 則各分送到台灣、日本：舊算法 140 筆會上榜，實際只有 70 則
        foreach (range(1, 70) as $number) {
            $this->seedOutfit(++$id, 'tw', "a{$number}", ['450001']);
            $this->seedOutfit(++$id, 'jp', "a{$number}", ['450001']);
        }
        foreach (range(1, 101) as $number) {
            $this->seedOutfit(++$id, 'tw', "b{$number}", ['450002']);
        }

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        app(HmallProductRepository::class)->setTopWearingHmallProductsCache();

        $this->assertNotEmpty(array_filter(
            $statements,
            fn ($sql) => str_contains($sql, '(select /*+ JOIN_ORDER(style_hint_items, style_hints) */ ')
        ), '查詢提示要緊接在子查詢的 select 後面才有作用');

        $ranked = app(HmallProductRepository::class)->getTopWearingHmallProducts();
        $this->assertSame([$popular->id], $ranked->pluck('id')->all());
        $this->assertSame(101, (int) $ranked->first()->count);
        $this->assertSame(1, $popular->fresh()->top_wearing_rank);
        $this->assertNull($inflated->fresh()->top_wearing_rank);
    }

    /**
     * @param  array<int, string>  $codes
     * @param  array<int, string>  $colors
     */
    private function seedOutfit(int $id, string $country, ?string $sourceOutfitId, array $codes, array $colors = ['09']): void
    {
        DB::table('style_hints')->insert([
            'id' => $id,
            'outfit_id' => "outfit-{$id}",
            'country' => $country,
            'source_outfit_id' => $sourceOutfitId,
        ]);

        foreach ($codes as $code) {
            foreach ($colors as $color) {
                DB::table('style_hint_items')->insert([
                    'style_hint_id' => $id,
                    'code' => $code,
                    'original_product_id' => "{$code}-{$color}",
                ]);
            }
        }
    }

    private function createProduct(string $code): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => 'UNIQLO',
            'name' => "測試商品 {$code}",
            'code' => $code,
            'product_code' => "u{$code}",
            'gender' => '男裝',
            'sex' => '男裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 490,
            'lowest_record_price' => 490,
            'highest_record_price' => 490,
        ]));
    }
}
