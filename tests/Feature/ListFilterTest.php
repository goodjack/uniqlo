<?php

namespace Tests\Feature;

use App\Enums\ProductTag;
use App\Services\ListService;
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
            'sort' => ['?sort[]=discount'],
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

    public function test_the_sort_menu_lists_every_option_and_marks_the_current_one(): void
    {
        $response = $this->get(route('lists.sale').'?sort=discount')->assertOk();

        $response->assertSeeInOrder(['排序：預設', '排序：價格由低到高', '排序：降價最多']);
        $this->assertSame(['discount'], $this->selectedSortValues($response->getContent()));
        // 副標題的排序說法跟著換
        $response->assertSee(ListService::SORTS[ListService::SORT_DISCOUNT]['summary']);
    }

    public function test_an_unknown_sort_falls_back_to_the_default_order(): void
    {
        $response = $this->get(route('lists.sale').'?sort=discount-asc')->assertOk();

        $this->assertSame([''], $this->selectedSortValues($response->getContent()));
        $response->assertDontSee(ListService::SORTS[ListService::SORT_DISCOUNT]['summary']);
    }

    public function test_a_json_get_request_reads_filters_from_the_url_only(): void
    {
        $content = $this->call(
            'GET',
            route('lists.sale').'?tags=abc&q[]=x',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['q' => 'from-body', 'tags' => ['sale']]),
        )->assertOk()->getContent();

        $this->assertStringNotContainsString('from-body', $content);
        $this->assertSame([], $this->appliedTagLabels($content));
    }

    public function test_invalid_tags_are_dropped_while_valid_ones_still_apply(): void
    {
        $content = $this->get(route('lists.sale').'?tags[]=sale&tags[]=nope')
            ->assertOk()
            ->getContent();

        $this->assertSame([ProductTag::Sale->label()], $this->appliedTagLabels($content));
        // 不認得的標籤也不能跟著表單與連結傳下去
        $this->assertStringNotContainsString('nope', $content);
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

    public function test_each_applied_tag_link_removes_just_that_tag(): void
    {
        $content = $this->get(route('lists.sale').'?brand=GU&tags[]=sale&tags[]=new')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'href="'.e(route('lists.sale').'?'.http_build_query(['brand' => 'GU', 'tags' => ['new']])).'"',
            $content
        );
        $this->assertStringContainsString(
            'href="'.e(route('lists.sale').'?'.http_build_query(['brand' => 'GU', 'tags' => ['sale']])).'"',
            $content
        );
        $this->assertStringContainsString('href="'.e(route('lists.sale').'?brand=GU').'"', $content);
    }

    public function test_removing_a_tag_keeps_a_query_of_zero(): void
    {
        $content = $this->get(route('lists.sale').'?q=0&tags[]=sale')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.e(route('lists.sale').'?q=0').'"', $content);
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

    /**
     * @return array<int, string>
     */
    private function selectedSortValues(string $content): array
    {
        return array_map(
            fn ($node) => $node->getAttribute('value'),
            iterator_to_array($this->xpath($content)->query('//select[@name="sort"]/option[@selected]'))
        );
    }

    /**
     * 已套用的標籤（可以點掉的那排）。
     *
     * @return array<int, string>
     */
    private function appliedTagLabels(string $content): array
    {
        return array_map(
            fn ($node) => trim($node->textContent),
            iterator_to_array($this->xpath($content)->query('//a[starts-with(@aria-label, "拿掉「")]'))
        );
    }

    private function xpath(string $content): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$content);

        return new \DOMXPath($dom);
    }
}
