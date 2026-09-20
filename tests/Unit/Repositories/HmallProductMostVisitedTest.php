<?php

namespace Tests\Unit\Repositories;

use App\Repositories\HmallProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\Analytics\Analytics;
use Tests\TestCase;

class HmallProductMostVisitedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GA 回來的網址片段不可以被拼進 ORDER BY。
     *
     * 熱門排行的商品編號來自 GA 的 fullPageUrl，而 GA 收得到任何人亂打的網址：
     * 反覆打 /hmall-products/<任意字串> 就算回 404，錯誤頁一樣載了 GA 的 script，
     * 那個路徑還是會被記成一筆 pagePath，再被前 100 名的過濾條件收進來。以前
     * ORDER BY 那段是把編號加引號直接拼成字串，等於讓外部寫得進 SQL。
     */
    public function test_a_quoted_url_fragment_never_reaches_the_order_by_clause(): void
    {
        // 換掉容器裡的 Analytics（facade 的 shouldReceive() 會先去建真的那一個，
        // 測試環境沒有 GA 的 property ID，建不起來）
        $analytics = Mockery::mock(Analytics::class);
        $analytics->shouldReceive('get')->andReturn(collect([
            ['fullPageUrl' => "example.com/hmall-products/x',(select 1))-- ", 'screenPageViews' => 99],
            ['fullPageUrl' => 'example.com/hmall-products/u0000000053204', 'screenPageViews' => 10],
        ]));
        $this->app->instance('laravel-analytics', $analytics);

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query;
        });

        app(HmallProductRepository::class)->setMostVisitedHmallProductsCache();

        $ranked = array_values(array_filter(
            $statements,
            fn ($query) => str_contains($query->sql, 'FIELD(CONCAT(brand')
        ));

        // 查詢真的跑到了。少了這一條，查詢就算整個沒執行（例外被
        // setMostVisitedHmallProductsCache() 的 try/catch 吞掉）下面兩條也會過。
        $this->assertCount(1, $ranked);

        // 名次清單只剩佔位符，SQL 本文不含任何商品編號、引號或注入片段
        $this->assertStringContainsString(
            "FIELD(CONCAT(brand, '_', product_code), ?)",
            $ranked[0]->sql
        );
        $this->assertStringNotContainsString('select 1', $ranked[0]->sql);

        // 過不了白名單的那筆整個被丟掉，連 binding 都不該出現；
        // 合格的編號 whereIn 與 ORDER BY 各綁一次
        $this->assertNotContains("UNIQLO_x',(select 1))-- ", $ranked[0]->bindings);
        $this->assertCount(
            2,
            array_keys($ranked[0]->bindings, 'UNIQLO_u0000000053204', true)
        );
    }
}
