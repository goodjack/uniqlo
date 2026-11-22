<?php

namespace Tests\Unit\Repositories;

use App\Models\StyleHint;
use App\Models\StyleHintItem;
use App\Repositories\StyleHintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StyleHintDetectedItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_each_detected_category_and_color_once_per_outfit(): void
    {
        $this->save([
            $this->attribute('Shoe', 'shoes', 'black'),
            $this->attribute('Boot', 'shoes', 'black'),
            $this->attribute('Footwear', 'shoes', 'black'),
            $this->attribute('Pants', 'bottoms', 'blue'),
            $this->attribute('Handbag', 'bags', 'brown'),
        ]);

        $this->assertEquals([
            ['category' => 'shoes', 'color' => 'black'],
            ['category' => 'bottoms', 'color' => 'blue'],
            ['category' => 'bags', 'color' => 'brown'],
        ], StyleHint::sole()->detected_items);
    }

    public function test_entries_missing_a_category_or_color_are_skipped(): void
    {
        $this->save([
            $this->attribute('Shoe', 'shoes', ''),
            ['object_label' => 'Top', 'intermediate_category' => 'tops'],
            $this->attribute('Top', 'tops', 'gray'),
        ]);

        $this->assertEquals([['category' => 'tops', 'color' => 'gray']], StyleHint::sole()->detected_items);
    }

    public function test_an_outfit_without_detections_is_stored_as_empty_rather_than_unknown(): void
    {
        $this->save([]);

        $this->assertSame([], StyleHint::sole()->detected_items);
    }

    public function test_a_response_without_the_field_keeps_what_was_stored(): void
    {
        $this->save([$this->attribute('Shoe', 'shoes', 'black')]);
        $this->save(null);

        $this->assertEquals([['category' => 'shoes', 'color' => 'black']], StyleHint::sole()->detected_items);
    }

    public function test_a_new_outfit_from_a_response_without_the_field_has_no_detections(): void
    {
        $this->save(null);

        $this->assertNull(StyleHint::sole()->detected_items);
    }

    public function test_it_keeps_the_size_worn_for_each_tagged_product(): void
    {
        $this->save([], products: [
            ['product_id' => '360889-03', 'item_size' => 'S'],
            ['product_id' => '359088-67', 'item_size' => '25inch'],
            ['product_id' => '360790-09'],
        ]);

        $this->assertSame(
            ['359088-67' => '25inch', '360790-09' => null, '360889-03' => 'S'],
            StyleHintItem::orderBy('original_product_id')->pluck('item_size', 'original_product_id')->all()
        );
    }

    public function test_a_response_without_the_size_keeps_the_stored_size(): void
    {
        $this->save([], products: [['product_id' => '360889-03', 'item_size' => 'S']]);
        $this->save([], products: [['product_id' => '360889-03']]);

        $this->assertSame(['360889-03' => 'S'], StyleHintItem::pluck('item_size', 'original_product_id')->all());
    }

    public function test_a_new_size_replaces_the_stored_one(): void
    {
        $this->save([], products: [['product_id' => '360889-03', 'item_size' => 'S']]);
        $this->save([], products: [['product_id' => '360889-03', 'item_size' => 'M']]);

        $this->assertSame(['360889-03' => 'M'], StyleHintItem::pluck('item_size', 'original_product_id')->all());
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $attributes  null 代表回應裡沒有這個欄位
     */
    /**
     * @param  array<int, array{product_id: string, item_size?: string}>  $products
     */
    private function save(?array $attributes, array $products = []): void
    {
        $content = [
            'id_ugc_dist_content' => '16693239',
            'image_original' => 'https://api.fastretailing.com/ugc/v1/gu/tw/SR_IMAGES/ugc_stylehint_gu_tw_photo_261017_2017561',
            'link_to_url' => 'https://www.stylehint.com/tw/tw/outfit/2017561',
            'gender' => 2,
            'model_height' => 159,
            'products' => array_map(fn (array $product) => (object) ['ugc_item' => (object) $product], $products),
        ];

        if ($attributes !== null) {
            $content['attributes'] = array_map(fn (array $attribute) => (object) $attribute, $attributes);
        }

        app(StyleHintRepository::class)->saveStyleHintsFromUgc([(object) $content], 'GU');
    }

    /**
     * @return array<string, mixed>
     */
    private function attribute(string $label, string $category, string $color): array
    {
        return [
            'id_ugc_content_detail' => random_int(1, 99999999),
            'box' => [0.5, 0.96, 0.35, 0.71],
            'score' => 0.86,
            'object_label' => $label,
            'detected_color' => $color,
            'intermediate_category' => $category,
        ];
    }
}
