<?php

namespace Tests\Unit\Services;

use App\Services\SitemapService;
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
}
