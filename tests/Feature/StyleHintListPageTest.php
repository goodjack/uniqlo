<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StyleHintListPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_page_counts_and_pages_distinct_outfits_preferring_taiwan(): void
    {
        $this->createProduct('450001');
        foreach (range(1, 33) as $number) {
            $this->seedOutfit($number * 2 - 1, 'tw', (string) (900 + $number));
            $this->seedOutfit($number * 2, 'jp', (string) (900 + $number));
        }

        $url = route('uniqlo-style-hints.show', ['uniqlo_product_code' => 'u450001']);

        $this->get($url)->assertOk()->assertSee('共 33 張 StyleHint 網友穿搭靈感')->assertDontSee('/jp/ja/stylehint/');
        $this->get($url.'?page=2')->assertOk()
            ->assertSee('stylingbook/stylehint/outfit-1', false)
            ->assertDontSee('網友穿搭靈感 34 (');
    }

    private function seedOutfit(int $id, string $country, string $sourceOutfitId): void
    {
        DB::table('style_hints')->insert([
            'id' => $id,
            'outfit_id' => "outfit-{$id}",
            'country' => $country,
            'source_outfit_id' => $sourceOutfitId,
        ]);
        DB::table('style_hint_items')->insert([
            'style_hint_id' => $id,
            'code' => '450001',
            'original_product_id' => '450001-09',
        ]);
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
