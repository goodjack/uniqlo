<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCategory('all_women', '女裝', null, CategoryLevel::Top);
        $this->createCategory('all_women-tops', '上衣類', 'all_women', CategoryLevel::One);
        $this->createCategory('all_women-tops-tshirt', 'T恤', 'all_women-tops', CategoryLevel::Two);
        $this->createCategory('all_women-tops-anchor01', '女裝/男女適穿', 'all_women-tops', CategoryLevel::Three);
        $this->createCategory('all_women-empty', '沒有商品的分類', 'all_women', CategoryLevel::One);
    }

    public function test_overview_lists_categories_that_still_have_products(): void
    {
        $this->attachProduct($this->createProduct(['name' => '短袖上衣']), 'all_women-tops');

        $response = $this->get(route('categories.index'));

        $response->assertOk();
        $response->assertSee('女裝');
        $response->assertSee('上衣類');
        $response->assertDontSee('沒有商品的分類');
        $response->assertSee('uq-cat-list');
        $response->assertDontSee('uq-pill-small');
    }

    public function test_category_page_shows_its_products_and_child_categories(): void
    {
        $this->attachProduct($this->createProduct(['name' => '短袖上衣']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['name' => '長袖T恤']), 'all_women-tops-tshirt');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']));

        $response->assertOk();
        $response->assertSee('短袖上衣');
        $response->assertSee('T恤');
        $response->assertSee('uq-cat-list');
        $response->assertDontSee('uq-pill-small');
    }

    /**
     * 分類主檔只增不減，商品全部下架的分類仍留在表裡。
     */
    public function test_category_without_available_products_returns_404(): void
    {
        $soldOut = $this->createProduct(['name' => '已售完', 'stock' => 'N']);
        $this->attachProduct($soldOut, 'all_women-empty');

        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-empty']))->assertNotFound();
    }

    /**
     * 只有大類與品項開頁面：頂層太廣，錨點那層的名稱當標題不成句。
     */
    public function test_only_level_one_and_two_have_pages(): void
    {
        $this->attachProduct($this->createProduct(), 'all_women');
        $this->attachProduct($this->createProduct(), 'all_women-tops-anchor01');

        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women']))->assertNotFound();
        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops-anchor01']))->assertNotFound();
    }

    /**
     * 分類屬於哪一家是由它底下的商品決定的，網址的品牌對不上就不是同一個資源。
     */
    public function test_a_category_is_not_reachable_under_the_wrong_brand(): void
    {
        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO']), 'all_women-tops');

        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))->assertOk();
        $this->get(route('categories.show', ['brand' => 'gu', 'code' => 'all_women-tops']))->assertNotFound();
    }

    public function test_unknown_category_code_returns_404(): void
    {
        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'does-not-exist']))->assertNotFound();
    }

    /**
     * 兩家的分類名稱會撞，總覽要標出是誰的分類。
     */
    public function test_overview_labels_which_brand_each_group_belongs_to(): void
    {
        $this->createCategory('women_all', 'WOMEN', null, CategoryLevel::Top, 'GU');
        $this->createCategory('women_knitandcardigan', '針織上衣', 'women_all', CategoryLevel::One, 'GU');

        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['brand' => 'GU']), 'women_knitandcardigan');

        $response = $this->get(route('categories.index'));

        $response->assertOk();
        $response->assertSee('UNIQLO');
        $response->assertSee('GU');
        $response->assertSee('針織上衣');
    }

    /**
     * 照品牌字串排 GU 會在前面，但站的主體是 UNIQLO。
     */
    public function test_the_overview_puts_uniqlo_before_gu(): void
    {
        $this->createCategory('women_all', 'WOMEN', null, CategoryLevel::Top, 'GU');
        $this->createCategory('women_knitandcardigan', '針織上衣', 'women_all', CategoryLevel::One, 'GU');

        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['brand' => 'GU']), 'women_knitandcardigan');

        $content = $this->get(route('categories.index'))->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$content);
        $xpath = new \DOMXPath($dom);

        $headings = [];

        foreach ($xpath->query('//h2[contains(@class, "uq-brand-h2")]') as $heading) {
            $headings[] = trim($heading->textContent);
        }

        $this->assertSame(['UNIQLO', 'GU'], $headings, '品牌區塊的順序是先 UNIQLO 再 GU');
    }

    /**
     * 女裝三個大類、男裝一個：照 code 排男裝會在前，照大類數排女裝才在前。
     */
    public function test_the_overview_puts_groups_with_more_child_categories_first(): void
    {
        $this->createCategory('all_men', '男裝', null, CategoryLevel::Top);
        $this->createCategory('all_men-tops', '男裝上衣類', 'all_men', CategoryLevel::One);
        $this->createCategory('all_women-bottoms', '下身類', 'all_women', CategoryLevel::One);
        $this->createCategory('all_women-inner', '內衣類', 'all_women', CategoryLevel::One);

        foreach (['all_men-tops', 'all_women-tops', 'all_women-bottoms', 'all_women-inner'] as $code) {
            $this->attachProduct($this->createProduct(), $code);
        }

        $groups = app(CategoryService::class)->getOverview();

        $this->assertSame(
            ['all_women', 'all_men'],
            $groups->pluck('category.code')->all(),
            '三個大類的女裝要排在只有一個大類的男裝前面'
        );
    }

    /**
     * T恤兩件、襯衫一件：照 code 排襯衫會在前，照商品數排 T恤才在前。
     */
    public function test_child_categories_are_ordered_by_how_many_products_they_have(): void
    {
        $this->createCategory('all_women-tops-shirt', '襯衫', 'all_women-tops', CategoryLevel::Two);

        $this->attachProduct($this->createProduct(['name' => '長袖T恤']), 'all_women-tops-tshirt');
        $this->attachProduct($this->createProduct(['name' => '短袖T恤']), 'all_women-tops-tshirt');
        $this->attachProduct($this->createProduct(['name' => '素面襯衫']), 'all_women-tops-shirt');

        $parent = HmallCategory::where('brand', 'UNIQLO')->where('code', 'all_women-tops')->firstOrFail();
        $children = app(CategoryService::class)->getChildren($parent);

        $this->assertSame(['all_women-tops-tshirt', 'all_women-tops-shirt'], $children->pluck('code')->all());
        $this->assertSame([2, 1], $children->pluck('hmall_products_count')->map(fn ($n) => (int) $n)->all());
    }

    /**
     * 章節選單的白底與底線要滿版，不能被頁面 container 限制在中間那欄。
     */
    public function test_the_section_menu_sits_outside_the_page_container(): void
    {
        $this->createCategory('women_all', 'WOMEN', null, CategoryLevel::Top, 'GU');
        $this->createCategory('women_knitandcardigan', '針織上衣', 'women_all', CategoryLevel::One, 'GU');

        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['brand' => 'GU']), 'women_knitandcardigan');

        $content = $this->get(route('categories.index'))->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$content);
        $xpath = new \DOMXPath($dom);

        $nested = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " container ")]'.
            '//*[contains(@class, "uq-section-menu")]'
        );

        $this->assertSame(0, $nested->length, '章節選單不該巢狀在任何 container 元素底下');
    }

    public function test_browsing_a_category_is_not_throttled(): void
    {
        $this->attachProduct($this->createProduct(), 'all_women-tops');
        $url = route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']);

        for ($i = 0; $i < 31; $i++) {
            $this->get($url)->assertOk();
        }
    }

    public function test_searching_within_a_category_is_throttled_like_search(): void
    {
        $this->attachProduct($this->createProduct(), 'all_women-tops');
        $url = route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops', 'q' => '上衣']);

        for ($i = 0; $i < 30; $i++) {
            $this->get($url)->assertOk();
        }

        $this->get($url)->assertStatus(429);
    }

    public function test_searching_within_a_category_does_not_use_up_the_site_search_limit(): void
    {
        $this->attachProduct($this->createProduct(), 'all_women-tops');
        $url = route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops', 'q' => '上衣']);

        for ($i = 0; $i < 30; $i++) {
            $this->get($url)->assertOk();
        }

        $this->get(route('search.index', ['query' => '上衣']))->assertOk();
    }

    /**
     * code 來自官網：站內產生的連結打回去，要拿到同一個分類。
     */
    #[DataProvider('unusualCodes')]
    public function test_links_to_a_category_with_unusual_characters_lead_back_to_it(string $code): void
    {
        $this->createCategory($code, '特殊分類', 'all_women', CategoryLevel::One);
        $this->attachProduct($this->createProduct(['name' => '特殊分類的商品']), $code);

        $overview = $this->get(route('categories.index'))->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$overview);
        $href = (new \DOMXPath($dom))
            ->query("//a[contains(normalize-space(.), '特殊分類')]/@href")
            ->item(0)
            ?->value;

        $this->assertNotNull($href);
        $this->get($href)
            ->assertOk()
            ->assertSee('特殊分類的商品')
            ->assertSee('<link rel="canonical" href="'.$href.'"', false);
    }

    public function test_pagination_on_a_category_whose_code_has_a_slash_stays_on_that_category(): void
    {
        $this->createCategory('tops/inner', '特殊分類', 'all_women', CategoryLevel::One);

        for ($i = 0; $i < 25; $i++) {
            $this->attachProduct($this->createProduct(['name' => "內搭 {$i}"]), 'tops/inner');
        }

        $url = \App\Support\Url::category(\App\Enums\Brand::Uniqlo, 'tops/inner');
        $content = $this->get($url)->assertOk()->getContent();

        $this->assertStringContainsString(e($url.'?page=2'), $content);
        $this->get($url.'?page=2')->assertOk()->assertSee('內搭');
    }

    public static function unusualCodes(): array
    {
        return [
            'slash' => ['tops/inner'],
            'hash' => ['tops#1'],
            'question mark' => ['tops?new'],
            'percent' => ['tops%20inner'],
            'space' => ['tops inner'],
            'zero width space' => ["kids-trend\u{200B}"],
        ];
    }

    /**
     * 分類本身有商品時，帶著任何未知的 query string 進來都不該變成 404。
     */
    public function test_an_unknown_query_string_does_not_turn_the_page_into_404(): void
    {
        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO', 'name' => '短袖上衣']), 'all_women-tops');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?ref=old');

        $response->assertOk();
        $response->assertSee('短袖上衣');
    }

    /**
     * 陣列型的 query 參數不該讓頁面 500。q 是分類內搜尋、query 是每一頁導覽列
     * 搜尋框讀的參數。
     *
     * @dataProvider arrayQueryStrings
     */
    public function test_an_array_query_string_does_not_break_the_category_page(string $queryString): void
    {
        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO', 'name' => '短袖上衣']), 'all_women-tops');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).$queryString);

        $response->assertOk();
        $response->assertSee('短袖上衣');
    }

    public static function arrayQueryStrings(): array
    {
        return [
            'unknown' => ['?ref[]=x'],
            'q' => ['?q[]=x'],
            'query' => ['?query[]=x'],
        ];
    }

    public function test_like_wildcards_in_the_query_are_escaped_on_the_category_page(): void
    {
        $this->attachProduct(
            $this->createProduct(['brand' => 'UNIQLO', 'name' => '100% 純棉短褲', 'code' => '900001']),
            'all_women-tops'
        );
        $this->attachProduct(
            $this->createProduct(['brand' => 'UNIQLO', 'name' => '牛仔短褲', 'code' => '900002']),
            'all_women-tops'
        );

        $response = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?q='.urlencode('%')
        );

        $response->assertOk();
        $response->assertSee('100% 純棉短褲');
        $response->assertDontSee('牛仔短褲');

        // 測試資料裡沒有底線，_ 當字面字元查應該完全沒有結果
        $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?q='.urlencode('_'))
            ->assertOk()
            ->assertSee('沒有符合「_」的商品');
    }

    /**
     * 篩到一件不剩時要有空狀態，否則看不出是篩太緊還是頁面壞掉。
     */
    public function test_a_category_filtered_down_to_nothing_shows_an_empty_state(): void
    {
        // identity 是空陣列，所以不會命中任何標籤
        $this->attachProduct($this->createProduct(['name' => '短袖上衣']), 'all_women-tops');

        $response = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?tags[]=coming-soon'
        );

        $response->assertOk();
        $response->assertDontSee('短袖上衣');
        $response->assertSee('沒有符合的商品');
        $response->assertSee('試試看少選幾個條件');
    }

    public function test_q_filters_the_category_by_name(): void
    {
        $this->attachProduct($this->createProduct(['name' => '牛仔超寬版短褲', 'code' => '359225']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['name' => 'AIRism 圓領T恤', 'code' => '474238']), 'all_women-tops');

        $response = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?q='.urlencode('短褲')
        );

        $response->assertOk();
        $response->assertSee('牛仔超寬版短褲');
        $response->assertDontSee('AIRism');
    }

    public function test_q_filters_the_category_by_code(): void
    {
        $this->attachProduct($this->createProduct(['name' => '牛仔超寬版短褲', 'code' => '359225']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['name' => 'AIRism 圓領T恤', 'code' => '474238']), 'all_women-tops');

        $response = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?q=474238'
        );

        $response->assertOk();
        $response->assertSee('AIRism');
        $response->assertDontSee('牛仔超寬版短褲');
    }

    public function test_q_with_multiple_keywords_requires_all_of_them(): void
    {
        $this->attachProduct($this->createProduct(['name' => '牛仔超寬版短褲', 'code' => '359225']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['name' => '亞麻混紡短褲', 'code' => '483042']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['name' => 'AIRism 圓領T恤', 'code' => '474238']), 'all_women-tops');

        $response = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops'])
                .'?q='.urlencode('短褲 牛仔')
        );

        $response->assertOk();
        $response->assertSee('牛仔超寬版短褲');
        $response->assertDontSee('亞麻混紡短褲');
        $response->assertDontSee('AIRism');
    }

    public function test_q_and_tags_apply_together_on_the_category_page(): void
    {
        $this->attachProduct($this->createProduct([
            'name' => '特價短褲',
            'code' => '111111',
            'identity' => json_encode(['concessional_rate']),
        ]), 'all_women-tops');
        $this->attachProduct($this->createProduct([
            'name' => '一般短褲',
            'code' => '222222',
            'identity' => '[]',
        ]), 'all_women-tops');
        $this->attachProduct($this->createProduct([
            'name' => '特價上衣',
            'code' => '333333',
            'identity' => json_encode(['concessional_rate']),
        ]), 'all_women-tops');

        $response = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops'])
                .'?q='.urlencode('短褲').'&tags[]=sale'
        );

        $response->assertOk();
        $response->assertSee('特價短褲');
        $response->assertDontSee('一般短褲');
        $response->assertDontSee('特價上衣');
    }

    public function test_q_with_no_match_shows_the_empty_state_on_the_category_page(): void
    {
        $this->attachProduct($this->createProduct(['name' => '牛仔超寬版短褲']), 'all_women-tops');

        $response = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops'])
                .'?q='.urlencode('這個關鍵字不會有任何商品符合')
        );

        $response->assertOk();
        $response->assertSee('沒有符合「這個關鍵字不會有任何商品符合」的商品');
    }

    public function test_a_category_page_with_q_is_not_indexed(): void
    {
        $this->attachProduct($this->createProduct(['name' => '牛仔超寬版短褲']), 'all_women-tops');

        $withQuery = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?q='.urlencode('短褲')
        )->assertOk()->getContent();

        $withoutQuery = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops'])
        )->assertOk()->getContent();

        $this->assertStringContainsString('noindex', $withQuery);
        $this->assertStringNotContainsString('noindex', $withoutQuery);
    }

    /**
     * 分類頁有分頁，即時篩只篩得到這一頁，會讓人誤以為篩了整個分類，所以只
     * 按 Enter 送出。停止輸入就自動送出會打斷注音組字，不要改回去。
     */
    public function test_the_search_input_does_not_enable_instant_filtering(): void
    {
        $this->attachProduct($this->createProduct(['name' => '牛仔超寬版短褲']), 'all_women-tops');

        $content = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops'])
        )->assertOk()->getContent();

        $this->assertStringNotContainsString('data-instant-filter', $content);
        $this->assertStringNotContainsString('data-debounce-submit', $content);
        $this->assertStringContainsString('name="q"', $content);
    }

    public function test_the_search_form_action_has_no_page_query_so_a_new_keyword_lands_on_page_one(): void
    {
        $this->attachProduct($this->createProduct(['name' => '牛仔超寬版短褲']), 'all_women-tops');

        $content = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?q=短褲&page=3'
        )->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$content);
        $xpath = new \DOMXPath($dom);

        $form = $xpath->query("//form[contains(@class, 'uq-search-form')]")->item(0);

        $this->assertNotNull($form);
        $this->assertStringNotContainsString('page', (string) $form->getAttribute('action'));
    }

    public function test_pagination_links_keep_the_q_parameter(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->attachProduct($this->createProduct(['name' => "短褲 {$i} 號"]), 'all_women-tops');
        }

        $content = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?q='.urlencode('短褲')
        )->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$content);
        $xpath = new \DOMXPath($dom);

        $pageTwoLinks = $xpath->query("//div[contains(@class, 'uq-pagination')]//a[contains(text(), '2')]");

        $this->assertGreaterThan(0, $pageTwoLinks->length);

        $href = $pageTwoLinks->item(0)->getAttribute('href');

        $this->assertStringContainsString('page=2', $href);
        $this->assertStringContainsString('q=', $href);
    }

    public function test_pagination_links_keep_both_q_and_tags(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->attachProduct($this->createProduct([
                'name' => "特價短褲 {$i} 號",
                'identity' => json_encode(['concessional_rate']),
            ]), 'all_women-tops');
        }

        $content = $this->get(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops'])
                .'?q='.urlencode('短褲').'&tags[]=sale'
        )->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$content);
        $xpath = new \DOMXPath($dom);

        $pageTwoLinks = $xpath->query("//div[contains(@class, 'uq-pagination')]//a[contains(text(), '2')]");

        $this->assertGreaterThan(0, $pageTwoLinks->length);

        $href = $pageTwoLinks->item(0)->getAttribute('href');

        $this->assertStringContainsString('page=2', $href);
        $this->assertStringContainsString('tags%5B0%5D=sale', $href);
        $this->assertStringContainsString('q=', $href);
    }

    public function test_products_follow_the_official_sort_within_the_category(): void
    {
        $this->attachProduct($this->createProduct(['name' => '排在後面的']), 'all_women-tops', '006002009');
        $this->attachProduct($this->createProduct(['name' => '排在前面的']), 'all_women-tops', '006002001');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']));

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, '排在後面的'),
            strpos($content, '排在前面的'),
        );
    }

    /**
     * 分類頁走 SELECT_COLUMNS_FOR_LIST 挑過欄位的查詢，漏掉欄位時卡片上那一行
     * 會無聲消失。
     */
    public function test_category_page_cards_show_the_price_range(): void
    {
        $this->attachProduct($this->createProduct([
            'name' => '特價上衣',
            'min_price' => 490,
            'highest_record_price' => 790,
            'lowest_record_price' => 390,
        ]), 'all_women-tops');

        $content = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$content);
        $range = (new \DOMXPath($dom))
            ->query('//div[contains(@class, "card")]//div[contains(@class, "sub")][contains(@class, "header")]');

        $this->assertSame(1, $range->length, '卡片要有一行歷史區間');
        $this->assertStringContainsString('790', $range->item(0)->textContent);
        $this->assertStringContainsString('390', $range->item(0)->textContent);
        $this->assertStringNotContainsString('原價', $content, '區間已經講完了，不再重複一行原價');
    }

    public function test_category_page_uses_the_shared_pagination_component(): void
    {
        foreach (range(1, 25) as $index) {
            $this->attachProduct($this->createProduct([
                'code' => "45900{$index}",
                'product_code' => "u45900{$index}",
            ]), 'all_women-tops');
        }

        $content = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ts small buttons', $content);
        $this->assertStringContainsString('icon button', $content);
        $this->assertStringNotContainsString('uq-pagination-current', $content);
    }

    /**
     * 頂層沒有自己的頁面，只當文字不做連結。
     */
    public function test_category_page_shows_a_breadcrumb_back_to_the_root(): void
    {
        $this->attachProduct($this->createProduct(), 'all_women-tops-tshirt');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops-tshirt']));

        $response->assertOk();
        $response->assertSee('商品分類');
        $response->assertSee('女裝');
        $response->assertSee(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']));
    }

    /**
     * 分類的身分是品牌加 code：兩家同 code 時，父子關係不能串到另一家。
     */
    public function test_a_category_never_borrows_the_other_brands_tree(): void
    {
        $this->createCategory('all_top', 'UNIQLO 全部商品', null, CategoryLevel::Top, 'UNIQLO');
        $this->createCategory('all_top', 'GU 全部商品', null, CategoryLevel::Top, 'GU');

        $uniqloOne = $this->createCategory('shared_tops', '上衣類', 'all_top', CategoryLevel::One, 'UNIQLO');
        $guOne = $this->createCategory('shared_tops', 'TOPS', 'all_top', CategoryLevel::One, 'GU');

        $this->createCategory('uniqlo_tshirt', 'UNIQLO T恤', 'shared_tops', CategoryLevel::Two, 'UNIQLO');
        $this->createCategory('gu_tshirt', 'GU T恤', 'shared_tops', CategoryLevel::Two, 'GU');

        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO']), 'uniqlo_tshirt');
        $this->attachProduct($this->createProduct(['brand' => 'GU']), 'gu_tshirt');

        $this->assertSame('UNIQLO 全部商品', $uniqloOne->parent->name);
        $this->assertSame('GU 全部商品', $guOne->parent->name);

        $service = app(CategoryService::class);
        $this->assertSame(['uniqlo_tshirt'], $service->getChildren($uniqloOne)->pluck('code')->all());
        $this->assertSame(['gu_tshirt'], $service->getChildren($guOne)->pluck('code')->all());
    }

    /**
     * 錨點那層開不出頁面，列出來就是一整排 404。
     */
    public function test_a_level_two_page_does_not_link_to_level_three_categories(): void
    {
        $this->createCategory('all_women-tops-tshirt-anchor01', '女裝/短袖', 'all_women-tops-tshirt', CategoryLevel::Three);

        $this->attachProduct($this->createProduct(['name' => '長袖T恤']), 'all_women-tops-tshirt');
        $this->attachProduct($this->createProduct(['name' => '短袖T恤']), 'all_women-tops-tshirt-anchor01');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops-tshirt']));

        $response->assertOk();
        $response->assertDontSee(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops-tshirt-anchor01'])
        );
    }

    public function test_a_level_one_page_still_lists_its_level_two_children(): void
    {
        $this->attachProduct($this->createProduct(['name' => '短袖上衣']), 'all_women-tops');
        $this->attachProduct($this->createProduct(['name' => '長袖T恤']), 'all_women-tops-tshirt');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']));

        $response->assertOk();
        $response->assertSee(
            route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops-tshirt'])
        );
    }

    /**
     * 男女適穿的商品會在男裝與女裝樹下各掛一份同名分類，
     * 商品頁只需要顯示一次。
     */
    public function test_product_page_lists_its_categories_without_duplicates(): void
    {
        $this->createCategory('all_men-tops', '上衣類', 'all_women', CategoryLevel::One);
        $this->createCategory('all_men-tops-tshirt', 'T恤', 'all_men-tops', CategoryLevel::Two);

        $product = $this->createProduct(['product_code' => 'u778899']);
        $this->attachProduct($product, 'all_women-tops-tshirt');
        $this->attachProduct($product, 'all_men-tops-tshirt');

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u778899']))
            ->assertOk()
            ->getContent();

        // 只看分類那一行，麵包屑本來就會連到其中一個「T恤」
        $links = $this->categoryLinksInLine($content);

        $this->assertNotEmpty($links, '商品頁應該列出所屬分類');

        $linksToWomen = in_array('all_women-tops-tshirt', $links, true);
        $linksToMen = in_array('all_men-tops-tshirt', $links, true);

        $this->assertTrue($linksToWomen || $linksToMen, '商品頁應該列出所屬分類');
        $this->assertFalse($linksToWomen && $linksToMen, '同名的分類只該出現一次');
    }

    /**
     * 分隔符不能在連結裡，否則 hover 底線會連著它一起畫。
     */
    public function test_the_categories_line_separator_is_not_inside_the_link(): void
    {
        $product = $this->createProduct(['product_code' => 'u778900']);
        $this->attachProduct($product, 'all_women-tops');
        $this->attachProduct($product, 'all_women-tops-tshirt');

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u778900']))
            ->assertOk()
            ->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$content);
        $xpath = new \DOMXPath($dom);

        $links = $xpath->query('//*[contains(@class, "uq-categories-line")]//a');

        $this->assertGreaterThan(1, $links->length, '這則測試需要至少兩個分類連結才驗得出分隔符');

        foreach ($links as $link) {
            $this->assertStringNotContainsString(
                '·',
                $link->textContent,
                '分隔符不該出現在連結文字裡'
            );
        }

        $this->assertGreaterThan(
            0,
            $xpath->query('//*[contains(@class, "uq-categories-line")][contains(@class, "middoted")]')->length,
            '分隔符交給 Tocas 的 .middoted 畫，不自己放節點'
        );
    }

    /**
     * @return array<int, string>
     */
    private function categoryLinksInLine(string $html): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $xpath = new \DOMXPath($dom);
        $links = $xpath->query("//*[contains(@class, 'uq-categories-line')]//a/@href");

        $codes = [];

        foreach ($links as $href) {
            $codes[] = basename(parse_url($href->value, PHP_URL_PATH));
        }

        return $codes;
    }

    private function createCategory(
        string $code,
        string $name,
        ?string $parent,
        CategoryLevel $level,
        string $brand = 'UNIQLO'
    ): HmallCategory {
        return HmallCategory::create([
            'brand' => $brand,
            'code' => $code,
            'name' => $name,
            'parent_code' => $parent,
            'level' => $level->value,
        ]);
    }

    private function createProduct(array $attributes = []): HmallProduct
    {
        static $sequence = 0;
        $sequence++;

        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => "測試商品 {$sequence}",
            'code' => "45000{$sequence}",
            'product_code' => "u45000{$sequence}",
            'sex' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
        ], $attributes)));
    }

    private function attachProduct(HmallProduct $product, string $categoryCode, string $sort = '001'): void
    {
        $category = HmallCategory::where('brand', $product->brand)
            ->where('code', $categoryCode)
            ->firstOrFail();

        $product->categories()->attach($category->id, ['sort' => $sort]);
    }
}
