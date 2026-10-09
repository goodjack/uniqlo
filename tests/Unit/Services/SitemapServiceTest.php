<?php

namespace Tests\Unit\Services;

use App\Enums\Brand;
use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Services\SitemapService;
use App\Support\Url as SiteUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SitemapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_every_list_page_and_skips_redirects(): void
    {
        $xml = $this->renderSitemap();

        $listRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name) => str_starts_with($name, 'lists.'));

        $this->assertNotEmpty($listRoutes);

        foreach ($listRoutes as $name) {
            $this->assertStringContainsString('<loc>'.route($name).'</loc>', $xml);
        }

        $this->assertStringNotContainsString('<loc>'.url('products/sales').'</loc>', $xml);
    }

    /**
     * 正式資料有分類 code 帶零寬空白；<loc> 要跟站內 route() 同一套編碼，
     * 否則對搜尋引擎是兩個網址。
     */
    public function test_a_category_code_with_special_characters_is_url_encoded_the_same_way_as_route(): void
    {
        $category = HmallCategory::create([
            'brand' => 'GU',
            'code' => "kids/trend#?\u{200B}",
            'name' => '兒童趨勢',
            'parent_code' => null,
            'level' => CategoryLevel::One->value,
        ]);

        $product = HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => 'GU',
            'name' => '測試商品',
            'code' => '900001',
            'product_code' => 'u900001',
            'sex' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
        ]));
        $product->categories()->attach($category->id, ['sort' => '001']);

        $xml = $this->renderSitemap();

        $expectedUrl = SiteUrl::category(Brand::Gu, $category->code);

        $this->assertStringContainsString("<loc>{$expectedUrl}</loc>", $xml);
        $this->assertStringContainsString('kids%2Ftrend%23%3F%E2%80%8B', $expectedUrl);
    }

    private function renderSitemap(): string
    {
        config(['app.sitemap_name' => '-test-'.uniqid()]);
        $path = public_path('sitemap'.config('app.sitemap_name').'.xml');

        try {
            app(SitemapService::class)->make();

            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
