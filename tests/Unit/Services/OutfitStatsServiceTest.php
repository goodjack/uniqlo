<?php

namespace Tests\Unit\Services;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Services\OutfitStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OutfitStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = '360889';

    private HmallProduct $product;

    private int $nextId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = $this->createProduct(self::CODE);
    }

    public function test_copies_of_one_outfit_in_several_countries_count_once(): void
    {
        $this->seedOutfit(['source' => 'a', 'country' => 'tw', 'gender' => 2]);
        $this->seedOutfit(['source' => 'a', 'country' => 'jp', 'gender' => 2]);
        $this->seedOutfit(['source' => 'a', 'country' => 'us', 'gender' => 2]);
        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS - 1, ['gender' => 1]);

        $stats = $this->service()->compute($this->product);

        $this->assertSame(OutfitStatsService::MIN_OUTFITS, $stats['outfits']);
        $this->assertSame(['men' => OutfitStatsService::MIN_OUTFITS - 1, 'women' => 1], $stats['genders']);
    }

    public function test_too_few_outfits_give_no_stats_and_clear_the_old_ones(): void
    {
        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS);
        $this->service()->refresh($this->product);
        $this->assertNotNull($this->service()->get($this->product));

        DB::table('style_hint_items')->where('style_hint_id', 1)->delete();

        $this->assertNull($this->service()->refresh($this->product));
        $this->assertNull($this->service()->get($this->product));
    }

    public function test_colors_count_outfits_per_color_of_this_product(): void
    {
        $this->seedOutfits(3, ['colors' => ['03']]);
        $this->seedOutfits(2, ['colors' => ['44']]);
        // 同一則標了這件的兩個顏色：兩個顏色各算一次
        $this->seedOutfit(['colors' => ['03', '44']]);
        // 日本、美國版的商品編號沒有色號
        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS - 6, ['product_ids' => ['E360889-000']]);

        $stats = $this->service()->compute($this->product);

        $this->assertSame([['code' => '03', 'count' => 4], ['code' => '44', 'count' => 3]], $stats['colors']);
        $this->assertSame(6, $stats['colored_outfits']);
    }

    public function test_genders_use_the_official_codes_and_skip_unknown_values(): void
    {
        $this->seedOutfits(6, ['gender' => 2]);
        $this->seedOutfits(2, ['gender' => 1]);
        $this->seedOutfits(1, ['gender' => 3]);
        $this->seedOutfits(1, ['gender' => null]);
        $this->seedOutfits(1, ['gender' => 9]);

        $stats = $this->service()->compute($this->product);

        $this->assertSame(['women' => 6, 'men' => 2, 'kids' => 1], $stats['genders']);
    }

    public function test_heights_are_grouped_in_five_centimetre_bands(): void
    {
        $heights = [157, 159, 155, 162, 164, '170', null, 0, 300, 'abc', 158];
        foreach ($heights as $height) {
            $this->seedOutfit(['height' => $height]);
        }

        $stats = $this->service()->compute($this->product);

        $this->assertSame([
            ['from' => 155, 'count' => 4],
            ['from' => 160, 'count' => 2],
            ['from' => 170, 'count' => 1],
        ], $stats['heights']);
        $this->assertSame(7, $stats['measured_outfits']);
    }

    public function test_paired_categories_count_each_outfit_once_per_category(): void
    {
        $sneakers = $this->createProduct('360001', '運動鞋');
        $boots = $this->createProduct('360002', '運動鞋');
        $pants = $this->createProduct('360003', '褲裝');

        $this->seedOutfits(3, ['others' => [$sneakers->code, $boots->code]]);
        $this->seedOutfits(2, ['others' => [$pants->code, $sneakers->code]]);
        // 資料庫裡沒有的商品不算
        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS - 5, ['others' => ['999999']]);

        $stats = $this->service()->compute($this->product);

        $this->assertSame([['name' => '運動鞋', 'count' => 5], ['name' => '褲裝', 'count' => 2]], $stats['paired_categories']);
    }

    /**
     * 同一個商品代號在資料庫裡可能有好幾筆，固定用最早建立的那筆的分類，每天算出來才一樣。
     */
    public function test_a_code_with_several_products_always_uses_the_earliest_one(): void
    {
        $this->createProduct('360001', '褲裝');
        $this->createProduct('360001', '裙子', productCode: 'u360001-later');

        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS, ['others' => ['360001']]);

        $stats = $this->service()->compute($this->product);

        $this->assertSame([['name' => '褲裝', 'count' => OutfitStatsService::MIN_OUTFITS]], $stats['paired_categories']);
    }

    public function test_detected_items_count_each_outfit_once_and_skip_outfits_never_detected(): void
    {
        $this->seedOutfits(3, ['detected' => [['shoes', 'black'], ['bottoms', 'blue']]]);
        $this->seedOutfits(1, ['detected' => [['shoes', 'black']]]);
        $this->seedOutfits(1, ['detected' => [['shoes', 'brown']]]);
        $this->seedOutfits(1, ['detected' => []]);
        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS - 6, ['detected' => null]);

        $stats = $this->service()->compute($this->product);

        $this->assertSame([
            ['category' => 'shoes', 'color' => 'black', 'count' => 4],
            ['category' => 'bottoms', 'color' => 'blue', 'count' => 3],
            ['category' => 'shoes', 'color' => 'brown', 'count' => 1],
        ], $stats['detected_pairs']);
        $this->assertSame(6, $stats['detected_outfits']);
    }

    public function test_sizes_count_outfits_per_size_worn_of_this_product(): void
    {
        $this->seedOutfits(4, ['sizes' => ['S']]);
        $this->seedOutfits(3, ['sizes' => ['M']]);
        // 同一則穿了這件的兩個顏色、同一個尺寸，只算一次
        $this->seedOutfit(['colors' => ['03', '44'], 'sizes' => ['S', 'S']]);
        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS - 8, ['sizes' => [null]]);

        $stats = $this->service()->compute($this->product);

        $this->assertSame([['size' => 'S', 'count' => 5], ['size' => 'M', 'count' => 3]], $stats['sizes']);
        $this->assertSame(8, $stats['sized_outfits']);
    }

    /**
     * 同一件商品各則穿搭的尺寸寫法不一：「25inch」與「25」、「ONE SIZE」與「One size」是同一個尺寸。
     */
    public function test_size_labels_written_differently_count_as_one(): void
    {
        $this->seedOutfits(3, ['sizes' => ['25inch']]);
        $this->seedOutfits(2, ['sizes' => ['25']]);
        $this->seedOutfits(2, ['sizes' => [' One size ']]);
        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS - 7, ['sizes' => ['ONE SIZE']]);

        $stats = $this->service()->compute($this->product);

        $this->assertSame([['size' => '25', 'count' => 5], ['size' => 'ONE SIZE', 'count' => 5]], $stats['sizes']);
    }

    /**
     * 官方辨識會把商品本身也算進去：牛仔褲的穿搭幾乎都有「藍色褲子」，那是這件商品自己。
     */
    public function test_detected_items_leave_out_the_kind_of_item_this_product_is(): void
    {
        $this->product->forceFill(['name' => '喇叭牛仔褲'])->save();
        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS, ['detected' => [['bottoms', 'blue'], ['shoes', 'black']]]);

        $stats = $this->service()->compute($this->product);

        $this->assertSame([['category' => 'shoes', 'color' => 'black', 'count' => OutfitStatsService::MIN_OUTFITS]], $stats['detected_pairs']);
        $this->assertSame(OutfitStatsService::MIN_OUTFITS, $stats['detected_outfits']);
    }

    /**
     * @dataProvider productKinds
     */
    public function test_the_kind_of_item_is_read_from_the_product_name(string $name, ?string $category): void
    {
        $this->assertSame($category, OutfitStatsService::detectedCategoryOf($name));
    }

    public static function productKinds(): array
    {
        return [
            'jeans' => ['喇叭牛仔褲', 'bottoms'],
            'skirt' => ['燈芯絨短裙', 'skirts'],
            'tunic is a top, not a skirt' => ['TUNIC 細肩帶長版裙擺背心', 'tops'],
            'hoodie is a top, not a hat' => ['羅紋連帽上衣(長袖)', 'tops'],
            'zip hoodie jacket' => ['男女適穿 Heavy weight重磅內刷毛休閒連帽拉鍊外套', 'outerwears'],
            'beanie' => ['HEATTECH粗針織毛帽', 'hats'],
            'sneakers' => ['繫帶芭蕾運動鞋', 'shoes'],
            'bag' => ['2Way肩背包', 'bags'],
            'dress' => ['棉質長版洋裝', 'dress'],
            'tee' => ['AIRism棉質圓領T恤(短袖)', 'tops'],
            'unknown' => ['素面單色襪', null],
        ];
    }

    public function test_refresh_stores_what_the_product_page_reads(): void
    {
        $this->seedOutfits(OutfitStatsService::MIN_OUTFITS, ['colors' => ['09']]);

        $stats = $this->service()->refresh($this->product);

        $this->assertSame($stats, Cache::get('hmall_product:outfit_stats:'.self::CODE));
        $this->assertSame($stats, $this->service()->get($this->product));
    }

    private function service(): OutfitStatsService
    {
        return app(OutfitStatsService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedOutfits(int $count, array $attributes = []): void
    {
        foreach (range(1, $count) as $_) {
            $this->seedOutfit($attributes);
        }
    }

    /**
     * @param  array{source?: string, country?: string, gender?: int|null, height?: int|string|null, colors?: array<int, string>, sizes?: array<int, string|null>, product_ids?: array<int, string>, others?: array<int, string>, detected?: array<int, array{0: string, 1: string}>|null}  $attributes
     */
    private function seedOutfit(array $attributes = []): void
    {
        $id = ++$this->nextId;
        $detected = array_key_exists('detected', $attributes) ? $attributes['detected'] : null;

        DB::table('style_hints')->insert([
            'id' => $id,
            'outfit_id' => "outfit-{$id}",
            'country' => $attributes['country'] ?? 'tw',
            'source_outfit_id' => $attributes['source'] ?? "s{$id}",
            'gender' => array_key_exists('gender', $attributes) ? $attributes['gender'] : 2,
            'model_height' => array_key_exists('height', $attributes) ? $attributes['height'] : 160,
            'detected_items' => $detected === null ? null : json_encode(array_map(
                fn (array $pair) => ['category' => $pair[0], 'color' => $pair[1]],
                $detected
            )),
        ]);

        $productIds = $attributes['product_ids']
            ?? array_map(fn (string $color) => self::CODE."-{$color}", $attributes['colors'] ?? ['09']);
        $sizes = $attributes['sizes'] ?? [];

        foreach (array_values($productIds) as $index => $productId) {
            $this->insertItem($id, self::CODE, $productId, $sizes[$index] ?? null);
        }

        foreach ($attributes['others'] ?? [] as $code) {
            $this->insertItem($id, $code, "{$code}-09");
        }
    }

    private function insertItem(int $styleHintId, string $code, string $originalProductId, ?string $size = null): void
    {
        DB::table('style_hint_items')->insert([
            'style_hint_id' => $styleHintId,
            'code' => $code,
            'original_product_id' => $originalProductId,
            'item_size' => $size,
        ]);
    }

    private function createProduct(string $code, ?string $categoryName = null, ?string $productCode = null): HmallProduct
    {
        $product = HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => 'GU',
            'name' => "測試商品 {$code}",
            'code' => $code,
            'product_code' => $productCode ?? "u{$code}",
            'gender' => '女裝',
            'sex' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 490,
            'lowest_record_price' => 490,
            'highest_record_price' => 490,
        ]));

        if ($categoryName !== null) {
            $top = HmallCategory::firstOrCreate(
                ['brand' => 'GU', 'code' => 'women_all'],
                ['name' => '女裝', 'parent_code' => null, 'level' => CategoryLevel::Top->value]
            );
            $one = HmallCategory::firstOrCreate(
                ['brand' => 'GU', 'code' => "women_all-{$categoryName}"],
                ['name' => $categoryName, 'parent_code' => 'women_all', 'level' => CategoryLevel::One->value]
            );
            $two = HmallCategory::firstOrCreate(
                ['brand' => 'GU', 'code' => "women_all-{$categoryName}-all"],
                ['name' => $categoryName, 'parent_code' => $one->code, 'level' => CategoryLevel::Two->value]
            );

            foreach ([$top, $one, $two] as $category) {
                $product->categories()->attach($category->id, ['sort' => '001']);
            }
        }

        return $product;
    }
}
