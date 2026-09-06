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
     * 只有品牌、排序與搜尋關鍵字需要跨越篩選保留，其餘參數不該被表單帶著走。
     */
    public function test_the_filter_form_only_carries_brand_sort_and_q(): void
    {
        $content = $this->get(route('lists.sale').'?brand=GU&sort=price-asc&q='.urlencode('短褲').'&ref=old')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="brand" value="GU"', $content);
        $this->assertStringContainsString('name="sort" value="price-asc"', $content);
        $this->assertStringContainsString('name="q" value="短褲"', $content);
        $this->assertStringNotContainsString('name="ref"', $content);
    }

    /**
     * q 是超長輸入時要被裁掉，不能讓一般的頁面瀏覽因為驗證失敗而整頁壞掉。
     */
    public function test_an_overlong_query_is_truncated_instead_of_failing(): void
    {
        $longQuery = str_repeat('a', 60);
        $truncated = str_repeat('a', 50);

        $response = $this->get(route('lists.sale').'?q='.$longQuery);

        $response->assertOk();
        $response->assertSee('value="'.$truncated.'"', false);
        $response->assertDontSee($longQuery);
    }

    /**
     * q 前後的空白要修剪掉，不然「 短褲 」跟「短褲」在使用者眼中應該是同一次搜尋，
     * 卻會因為多出來的空白比對不到任何品名。
     */
    public function test_q_is_trimmed(): void
    {
        $response = $this->get(route('lists.sale').'?q='.urlencode('  短褲  '));

        $response->assertOk();
        $response->assertSee('value="短褲"', false);
    }

    /**
     * q 找不到符合的商品時要換成空狀態，文案點名關鍵字，
     * 不是清單頁預設的「沒有符合的商品」。
     */
    public function test_an_unmatched_query_shows_the_empty_state(): void
    {
        $response = $this->get(route('lists.sale').'?q='.urlencode('這個關鍵字不會有任何商品符合'));

        $response->assertOk();
        $response->assertSee('沒有符合「這個關鍵字不會有任何商品符合」的商品');
    }

    /**
     * 帶 q 的清單頁是既有清單的重組，不該被搜尋引擎索引，做法照搜尋結果頁。
     */
    public function test_a_list_page_with_q_is_not_indexed(): void
    {
        $withQuery = $this->get(route('lists.sale').'?q='.urlencode('短褲'))->assertOk()->getContent();
        $withoutQuery = $this->get(route('lists.sale'))->assertOk()->getContent();

        $this->assertStringContainsString('noindex', $withQuery);
        $this->assertStringNotContainsString('noindex', $withoutQuery);
    }
}
