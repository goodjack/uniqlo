<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 清單頁的標籤篩選表單。表單本身跟商品有沒有資料無關，所以這裡不建資料。
 */
class ListFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 篩選表單原本把其他 query 參數原封塞進 hidden input，
     * 遇到 ?ref[]=x 這種陣列就是把 array 丟給 Blade 轉字串，整頁 500。
     */
    public function test_an_array_query_string_does_not_break_the_list_page(): void
    {
        $response = $this->get(route('lists.sale').'?ref[]=x');

        $response->assertOk();
        $response->assertSee('篩選');
    }

    /**
     * 只有品牌與排序需要跨越篩選保留，其餘參數不該被表單帶著走。
     */
    public function test_the_filter_form_only_carries_brand_and_sort(): void
    {
        $content = $this->get(route('lists.sale').'?brand=GU&sort=price-asc&ref=old')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="brand" value="GU"', $content);
        $this->assertStringContainsString('name="sort" value="price-asc"', $content);
        $this->assertStringNotContainsString('name="ref"', $content);
    }
}
