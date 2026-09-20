<?php

namespace Tests\Unit\Services;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * sitemap 的清單頁是手寫的一份字串陣列，路由卻是另外一份。兩份各自長大，
 * 就會有頁面只存在其中一邊——lists/most-visited 真的這樣漏過一次：路由、
 * robots.txt、頁面的標準網址標籤都有它，只有 sitemap 沒有，而且沒有任何
 * 測試會發現。
 *
 * 這裡拿真的路由表去對，以後新增清單頁忘了回頭補 sitemap，這個測試就會紅。
 */
class SitemapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_every_list_page_route(): void
    {
        $routed = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name) => str_starts_with($name, 'lists.'))
            ->map(fn (string $name) => 'lists/'.substr($name, strlen('lists.')))
            ->sort()
            ->values()
            ->all();

        $this->assertNotEmpty($routed, '沒有抓到任何 lists 路由，這個測試本身壞了');

        $method = new ReflectionMethod(SitemapService::class, 'make');
        $source = file_get_contents($method->getFileName());

        $missing = array_values(array_filter(
            $routed,
            fn (string $page) => ! str_contains($source, "'".$page."'")
        ));

        $this->assertSame([], $missing, 'sitemap 少了這幾個清單頁：'.implode('、', $missing));
    }

    /**
     * 分類 code 曾經直接內插進 <loc>，沒有做 URL 編碼；正式資料裡 GU 有一筆
     * code 帶零寬空白（U+200B），沒編碼的 <loc> 跟站內 route() 產出的網址
     * （categories/index.blade.php 用的是同一個 route()）會變成兩個不同網址，
     * 對搜尋引擎來說是兩頁。sitemap 規格也要求 <loc> 必須是已編碼的網址。
     */
    public function test_a_category_code_with_special_characters_is_url_encoded_the_same_way_as_route(): void
    {
        config(['app.sitemap_name' => '-test-'.uniqid()]);

        $category = HmallCategory::create([
            'brand' => 'GU',
            'code' => "kids-trend\u{200B}",
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

        $path = null;

        try {
            app(SitemapService::class)->make();

            $fileName = 'sitemap'.config('app.sitemap_name').'.xml';
            $path = public_path($fileName);

            $this->assertFileExists($path);
            $xml = file_get_contents($path);

            $expectedUrl = route('categories.show', ['brand' => 'gu', 'code' => $category->code]);

            $this->assertStringContainsString(
                "<loc>{$expectedUrl}</loc>",
                $xml,
                'sitemap 的 <loc> 跟 route() 產出的網址編碼方式不一致'
            );
            // 零寬空白沒被編碼過的原字元不該出現在檔案裡
            $this->assertStringNotContainsString("kids-trend\u{200B}<", $xml);
        } finally {
            if ($path !== null && file_exists($path)) {
                unlink($path);
            }
        }
    }
}
