<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OutfitStatsSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => 'GU',
            'name' => '針織衫',
            'product_name' => '針織衫',
            'code' => '360889',
            'product_code' => 'u0000000053291',
            'sex' => '女裝',
            'gender' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 790,
            'lowest_record_price' => 790,
            'highest_record_price' => 790,
            'style_text' => json_encode(['03 GRAY', '44 YELLOW']),
            'color_nums' => json_encode(['COL03', 'COL44']),
        ]));
    }

    public function test_the_section_is_hidden_without_stats(): void
    {
        $this->assertStringNotContainsString('穿搭統計', $this->productPage());
    }

    public function test_the_section_shows_each_stat_as_a_share_of_the_outfits_it_covers(): void
    {
        Cache::forever('hmall_product:outfit_stats:360889', [
            'outfits' => 50,
            'colors' => [['code' => '03', 'count' => 14], ['code' => '44', 'count' => 13], ['code' => '99', 'count' => 1]],
            'colored_outfits' => 50,
            'genders' => ['women' => 47, 'men' => 3],
            'heights' => [['from' => 150, 'count' => 10], ['from' => 155, 'count' => 19], ['from' => 160, 'count' => 21]],
            'measured_outfits' => 50,
            'paired_categories' => [['name' => '鞋子', 'count' => 21], ['name' => '褲裝', 'count' => 9]],
            'detected_outfits' => 40,
            'detected_pairs' => [['category' => 'shoes', 'color' => 'black', 'count' => 30], ['category' => 'bags', 'color' => 'mauve', 'count' => 4]],
        ]);

        $page = $this->productPage();

        $this->assertStringContainsString('穿搭統計', $page);
        $this->assertStringContainsString('50 則', $page);
        // 顏色用官網的色名，現在不賣的顏色只寫色號
        $this->assertMatchesRegularExpression('/03 GRAY.*28%/s', $page);
        $this->assertMatchesRegularExpression('/色號 99.*2%/s', $page);
        $this->assertMatchesRegularExpression('/女.*94%.*男.*6%/s', $page);
        $this->assertMatchesRegularExpression('/155–159 公分.*38%/s', $page);
        $this->assertMatchesRegularExpression('/鞋子.*42%/s', $page);
        // 辨識結果以有辨識資料的則數為分母，沒翻譯的字照原文
        $this->assertMatchesRegularExpression('/黑色鞋子.*75%/s', $page);
        $this->assertMatchesRegularExpression('/mauve 包包.*10%/s', $page);
    }

    public function test_the_detected_colors_card_is_left_out_when_no_outfit_was_detected(): void
    {
        Cache::forever('hmall_product:outfit_stats:360889', [
            'outfits' => 10,
            'colors' => [],
            'colored_outfits' => 0,
            'genders' => ['women' => 10],
            'heights' => [],
            'measured_outfits' => 0,
            'paired_categories' => [],
            'detected_outfits' => 0,
            'detected_pairs' => [],
        ]);

        $page = $this->productPage();

        $this->assertStringContainsString('穿搭統計', $page);
        $this->assertStringContainsString('男女比例', $page);
        $this->assertStringNotContainsString('最多人穿的顏色', $page);
        $this->assertStringNotContainsString('搭配單品的顏色', $page);
    }

    /**
     * 整件商品夠則數，但每張卡都沒過自己的門檻：連標題都不出現，不留一個空區塊。
     */
    public function test_the_section_is_hidden_when_every_card_is_left_out(): void
    {
        $this->cacheStats(['genders' => ['women' => 9], 'measured_outfits' => 9, 'heights' => [['from' => 155, 'count' => 9]]]);

        $this->assertStringNotContainsString('穿搭統計', $this->productPage());
    }

    public function test_the_size_card_shows_sizes_worn_and_skips_one_size_products(): void
    {
        $this->cacheStats(['sizes' => [['size' => 'M', 'count' => 6], ['size' => 'S', 'count' => 4]], 'sized_outfits' => 10]);
        $this->assertMatchesRegularExpression('/最多人穿的尺寸.*M.*60%.*S.*40%/s', $this->productPage());

        $this->cacheStats(['sizes' => [['size' => 'ONE SIZE', 'count' => 10]], 'sized_outfits' => 10]);
        $this->assertStringNotContainsString('最多人穿的尺寸', $this->productPage());
    }

    public function test_a_card_based_on_too_few_outfits_is_left_out(): void
    {
        $this->cacheStats(['heights' => [['from' => 155, 'count' => 9]], 'measured_outfits' => 9]);

        $this->assertStringNotContainsString('身高分布', $this->productPage());
    }

    /**
     * 辨識結果的組合多，則數少或最常見的組合也不突出時不顯示，也不退回只看品類（幾乎每則都有鞋子，沒有資訊量）。
     */
    public function test_the_detected_colors_card_needs_enough_outfits_and_a_clear_leader(): void
    {
        $pairs = [['category' => 'shoes', 'color' => 'black', 'count' => 12], ['category' => 'bags', 'color' => 'brown', 'count' => 3]];

        $this->cacheStats(['detected_outfits' => 29, 'detected_pairs' => $pairs]);
        $this->assertStringNotContainsString('搭配單品的顏色', $this->productPage());

        $this->cacheStats(['detected_outfits' => 41, 'detected_pairs' => $pairs]);
        $this->assertStringNotContainsString('搭配單品的顏色', $this->productPage());

        $this->cacheStats(['detected_outfits' => 40, 'detected_pairs' => $pairs]);
        $page = $this->productPage();
        $this->assertMatchesRegularExpression('/搭配單品的顏色.*黑色鞋子.*30%/s', $page);
        // 低於一成的組合不列
        $this->assertStringNotContainsString('棕色包包', $page);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function cacheStats(array $overrides): void
    {
        Cache::forever('hmall_product:outfit_stats:360889', array_merge([
            'outfits' => 50,
            'colors' => [],
            'colored_outfits' => 0,
            'sizes' => [],
            'sized_outfits' => 0,
            'genders' => ['women' => 50],
            'heights' => [],
            'measured_outfits' => 0,
            'paired_categories' => [],
            'detected_outfits' => 0,
            'detected_pairs' => [],
        ], $overrides));
    }

    private function productPage(): string
    {
        return $this->get(route('gu-hmall-products.show', ['gu_product_code' => 'u0000000053291']))
            ->assertOk()
            ->getContent();
    }
}
