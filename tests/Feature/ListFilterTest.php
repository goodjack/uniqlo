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
     * 陣列型的 query 參數不該讓頁面 500。q 是清單內搜尋、query 是每一頁導覽列
     * 搜尋框讀的參數。
     *
     * @dataProvider arrayQueryStrings
     */
    public function test_an_array_query_string_does_not_break_the_list_page(string $queryString): void
    {
        $response = $this->get(route('lists.sale').$queryString);

        $response->assertOk();
        $response->assertSee('篩選');
    }

    public static function arrayQueryStrings(): array
    {
        return [
            'unknown' => ['?ref[]=x'],
            'q' => ['?q[]=x'],
            'query' => ['?query[]=x'],
        ];
    }

    /**
     * 舊連結、爬蟲或手改網址帶來的不合法值要丟掉、照常顯示，不能被轉走。
     */
    public function test_invalid_filter_values_are_dropped_instead_of_redirecting(): void
    {
        $content = $this->get(route('lists.sale').'?brand=ZARA&sort=oops&tags=abc')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="brand" value="ZARA"', $content);
        $this->assertStringNotContainsString('name="sort" value="oops"', $content);
    }

    public function test_invalid_tags_are_dropped_while_valid_ones_still_apply(): void
    {
        $this->get(route('lists.sale').'?tags[]=sale&tags[]=nope')
            ->assertOk();

        $this->assertSame(['sale'], request()->query('tags'));
    }

    /**
     * 篩選表單只帶品牌、排序與關鍵字，其餘參數不跟著走。
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

    public function test_an_overlong_query_is_truncated_instead_of_failing(): void
    {
        $longQuery = str_repeat('a', 60);
        $truncated = str_repeat('a', 50);

        $response = $this->get(route('lists.sale').'?q='.$longQuery);

        $response->assertOk();
        $response->assertSee('value="'.$truncated.'"', false);
        $response->assertDontSee($longQuery);
    }

    public function test_q_is_trimmed(): void
    {
        $response = $this->get(route('lists.sale').'?q='.urlencode('  短褲  '));

        $response->assertOk();
        $response->assertSee('value="短褲"', false);
    }

    public function test_an_unmatched_query_shows_the_empty_state(): void
    {
        $response = $this->get(route('lists.sale').'?q='.urlencode('這個關鍵字不會有任何商品符合'));

        $response->assertOk();
        $response->assertSee('沒有符合「這個關鍵字不會有任何商品符合」的商品');
    }

    /**
     * 帶 q 的清單頁是既有清單的重組，不該被索引。
     */
    public function test_a_list_page_with_q_is_not_indexed(): void
    {
        $withQuery = $this->get(route('lists.sale').'?q='.urlencode('短褲'))->assertOk()->getContent();
        $withoutQuery = $this->get(route('lists.sale'))->assertOk()->getContent();

        $this->assertStringContainsString('noindex', $withQuery);
        $this->assertStringNotContainsString('noindex', $withoutQuery);
    }
}
