<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 全站共同骨架的驗收：麵包屑只出現在分類樹上的那兩頁、最後一層都是當頁，
 * 卡片上的收藏鈕不會變成連結的子孫、卡片標籤最多兩個，商品頁右欄不再靠
 * 小標題分段。
 *
 * 這些都是「改一頁很容易忘記另外六頁」的類型，所以用一組測試把七種頁面
 * 一起釘住，而不是各自散在各頁的測試裡。
 */
class PageSkeletonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCategory('all_women', '女裝', null, CategoryLevel::Top);
        $this->createCategory('all_women-tops', '上衣類', 'all_women', CategoryLevel::One);
        $this->createCategory('all_women-tops-tshirt', 'T恤', 'all_women-tops', CategoryLevel::Two);
    }

    /**
     * 麵包屑是分類樹上的位置，往上一層要是有意義的去處。首頁是起點；清單、
     * 搜尋、收藏、分類總覽都是從導覽列直接進來的單層頁面，一條「首頁 › 自己」
     * 只是佔一行。
     *
     * @dataProvider pagesWithoutBreadcrumb
     */
    public function test_pages_outside_the_category_tree_have_no_breadcrumb(
        string $name,
        array $parameters
    ): void {
        $this->seedProduct();

        $content = $this->get(route($name, $parameters))->assertOk()->getContent();

        $this->assertSame([], $this->crumbs($content), "{$name} 不該有麵包屑");
    }

    public static function pagesWithoutBreadcrumb(): array
    {
        return [
            '首頁' => ['home', []],
            '清單頁' => ['lists.sale', []],
            '分類總覽' => ['categories.index', []],
            '搜尋結果' => ['search.show', ['query' => '外套']],
            '收藏' => ['favorites.index', []],
        ];
    }

    /**
     * @dataProvider pagesWithBreadcrumb
     */
    public function test_every_page_has_a_breadcrumb_that_ends_on_itself(
        string $name,
        array $parameters,
        string $expected
    ): void {
        $this->seedProduct();

        // 用 route() 產網址而不是寫死路徑：搜尋頁的關鍵字在路徑裡，要編碼過才配得到路由
        $url = route($name, $parameters);
        $content = $this->get($url)->assertOk()->getContent();
        $crumbs = $this->crumbs($content);

        $this->assertNotEmpty($crumbs, "{$name} 沒有麵包屑");
        $this->assertSame('首頁', $crumbs[0], "{$name} 的麵包屑第一層應該是首頁");
        $this->assertSame($expected, end($crumbs), "{$name} 的麵包屑最後一層應該是當頁");
        $this->assertSame($expected, $this->currentCrumb($content), "{$name} 的當頁那一層要標 aria-current");
    }

    public static function pagesWithBreadcrumb(): array
    {
        return [
            '分類頁' => ['categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops'], '上衣類'],
            // 商品頁的當頁那一層是完整商品名，也就是性別加名稱
            '商品頁' => ['uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001'], '女裝 短袖上衣'],
        ];
    }

    /**
     * 卡片整張原本是一個 <a>，收藏鈕放進去就是巢狀互動元素——無效的 HTML，
     * 無障礙樹裡也讀不出「這是另一顆按鈕」。
     *
     * @dataProvider pagesWithCards
     */
    public function test_the_card_favorite_button_is_never_inside_a_link(string $name, array $parameters): void
    {
        $this->seedProduct();

        $url = route($name, $parameters);
        $content = $this->get($url)->assertOk()->getContent();

        $this->assertGreaterThan(0, $this->countNodes($content, '//*[@data-favorite-card]'), "{$name} 的卡片上沒有收藏鈕");
        $this->assertSame(0, $this->countNodes($content, '//a//*[@data-favorite-card]'), "{$name} 的收藏鈕在連結裡面");
    }

    /**
     * 清單頁與首頁的卡片來自預熱好的快取，測試環境是空的、排不出卡片；
     * 商品頁的卡片要有延伸商品才會出現。這兩種都跟這裡驗的是同一個 partial，
     * 所以用分類頁與搜尋頁這兩條 DB 驅動的路徑就夠。
     */
    public static function pagesWithCards(): array
    {
        return [
            '分類頁' => ['categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']],
            '搜尋結果' => ['search.show', ['query' => '短袖上衣']],
        ];
    }

    /**
     * HmallProductPresenter::getProductTags() 以前直接 or 了 is_app_offer／
     * is_ec_only，跟 ProductTag::LimitedOffer->matches()（見
     * tests/Unit/Enums/ProductTagTest.php 的 test_app_and_ec_only_are_no_longer_limited_offers）
     * 各自認定不同：identity 只有 APP 的商品在清單頁與篩選不算期間限定，
     * 卡片上卻掛著「期間限定特價」。兩個判準已經統一成同一個 matches()，
     * APP 限定商品現在只掛「APP 限定特價」。
     */
    public function test_a_card_with_only_the_app_identity_does_not_get_the_limited_offer_label(): void
    {
        $this->seedProduct(['identity' => json_encode(['APP'])]);

        $content = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            0,
            $this->countNodes($content, '//*[contains(@class, "uq-card-status-item")][text()="期間限定特價"]'),
            'identity 只有 APP 不該掛期間限定特價'
        );
        $this->assertSame(
            1,
            $this->countNodes($content, '//*[contains(@class, "uq-card-status-item")][text()="APP 限定特價"]')
        );
    }

    /**
     * 第三輪 UI 拿掉「最多兩個加 +N」：站主判定高低不齊比漏資訊好接受，
     * 分類頁、清單頁與收藏清單現在全部一樣，標籤攤開來看得完。
     */
    public function test_a_card_shows_every_label(): void
    {
        $this->seedProduct(['identity' => json_encode([
            'time_doptimal', 'concessional_rate', 'new_product', 'multi_buy', 'revision',
        ])]);

        $content = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        $labels = $this->xpath($content)
            ->query('(//*[contains(@class, "uq-card-status")])[1]/span');

        // 四個：期間限定、特價、新款、合購。revision 是屬性標籤，只在商品頁
        $this->assertSame(4, $labels->length, '卡片上的標籤不再收合');
        $this->assertSame(0, $this->countNodes($content, '//*[contains(@class, "uq-label-more")]'));
    }

    /**
     * 商品頁的標籤是那個清單頁的入口：master 每個標籤都是 <a>，presenter 也一直
     * 算好了 url，v3 的 blade 沒讀它，標籤就變成純文字。有對應清單頁的才是連結
     * （歷史新低價、已售罄與六個屬性標籤沒有清單頁，維持純文字）。
     */
    public function test_the_product_page_labels_link_back_to_their_lists(): void
    {
        $this->seedProduct(['identity' => json_encode(['concessional_rate', 'revision'])]);

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            $this->countNodes(
                $content,
                '//a[contains(@class, "uq-price-status-item")][@href="'.route('lists.sale').'"][normalize-space()="特價商品"]'
            ),
            '特價商品要連回特價清單'
        );

        // icon 也照 master 補回來，一個標籤一個
        $this->assertSame(
            1,
            $this->countNodes(
                $content,
                '//a[contains(@class, "uq-price-status-item")][@href="'.route('lists.sale').'"]/i[contains(@class, "shopping")]'
            ),
            '標籤前面要有 icon'
        );

        $this->assertSame(
            0,
            $this->countNodes($content, '//a[contains(@class, "uq-price-status-item")][normalize-space()="修改褲長"]'),
            '沒有對應清單頁的標籤不做成連結'
        );
    }

    /**
     * 六個屬性標籤（豐富尺碼、男女適穿、旗艦店款、大型店商品、特定店商品、修改
     * 褲長）只在商品頁：它們講的是這件商品怎麼買、怎麼改，是決定要不要買的時候
     * 才要看的細節。master 也只有商品頁的 presenter 有，卡片那份沒有。
     */
    public function test_the_attribute_labels_stay_off_the_cards(): void
    {
        $this->seedProduct(['identity' => json_encode(['revision'])]);

        $cardContent = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            0,
            $this->countNodes($cardContent, '//*[contains(@class, "uq-card-status-item")][text()="修改褲長"]'),
            '卡片不掛屬性標籤'
        );

        $productContent = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            $this->countNodes($productContent, '//*[contains(@class, "uq-price-status-item")][normalize-space()="修改褲長"]'),
            '商品頁還是要列出屬性標籤'
        );
    }

    /**
     * 收藏清單一列只有一件商品，標籤攤開來看得完——而且那正是使用者追蹤它的原因。
     */
    public function test_the_favorites_list_shows_every_label(): void
    {
        $this->seedProduct(['identity' => json_encode([
            'time_doptimal', 'concessional_rate', 'new_product', 'multi_buy', 'revision',
        ])]);

        $content = $this->postJson(route('favorites.cards'), [
            'items' => [['brand' => 'UNIQLO', 'code' => 'u990001']],
        ])->assertOk()->getContent();

        $labels = $this->xpath($content)->query('//*[contains(@class, "uq-card-status")]/span');

        // 四個：期間限定、特價、新款、合購。revision 是屬性標籤，只在商品頁
        $this->assertSame(4, $labels->length, '收藏清單不收合標籤');
        $this->assertSame(0, $this->countNodes($content, '//*[contains(@class, "uq-label-more")]'));
    }

    /**
     * 商品頁右欄原本每一段各有一個小標題（標籤、所屬分類、商品資訊），三個標題
     * 加起來比它們標的內容還長。每一段長什麼樣子就說明它是什麼，標題全部拿掉。
     */
    public function test_the_product_page_right_column_has_no_section_headings(): void
    {
        $this->seedProduct();

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        foreach (['標籤', '所屬分類', '商品資訊'] as $heading) {
            $this->assertSame(
                0,
                $this->countNodes($content, "//h3[normalize-space()='{$heading}']"),
                "商品頁不該還有「{$heading}」這個小標題"
            );
        }

        $this->assertGreaterThan(
            0,
            $this->countNodes($content, '//dl[contains(@class, "uq-facts")]/div/dt'),
            '商品資訊改用 dl 呈現，不用 table'
        );
        $this->assertSame(0, $this->countNodes($content, '//table[contains(@class, "basic")]'));
    }

    /**
     * 商品說明是這一頁的正文。夠長就在桌機收合成一段加「顯示更多」，空的整塊不渲染。
     *
     * 本機資料庫沒有這個欄位的內容（正式機有），所以自己塞一段進去。
     */
    public function test_a_long_description_is_rendered_and_clamped(): void
    {
        // 收合框是十六行左右，這裡塞一百段、遠超過門檻，估算再怎麼保守都會收合
        $this->seedProduct(['instruction' => str_repeat('這是一段夠長的商品說明文字。', 100)]);

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, $this->countNodes($content, '//*[contains(@class, "uq-description")]'));
        $this->assertSame(1, $this->countNodes($content, '//div[contains(concat(\' \', normalize-space(@class), \' \'), \' uq-clamp \')]'), '桌機收合的外層');
        $this->assertSame(1, $this->countNodes($content, '//details[contains(@class, "uq-clamp-more")]'));
    }

    /**
     * 收合框照 master 是 400px、大約十六行。說明短到收起來也遮不住東西的時候
     * 不該長出「顯示更多」——那顆按下去畫面不會變。
     *
     * 說明裡常常一行一句，所以行數要照 <br> 拆開來估，不能只乘字數：第二段
     * 只有六十幾個字，但它是二十行。
     */
    public function test_a_short_description_is_not_clamped(): void
    {
        $this->seedProduct(['instruction' => '這是一段短說明。']);

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, $this->countNodes($content, '//*[contains(@class, "uq-description")]'));
        $this->assertSame(0, $this->countNodes($content, '//div[contains(concat(\' \', normalize-space(@class), \' \'), \' uq-clamp \')]'), '短說明不該收合');

        $this->seedShortLinesProduct();

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990002']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, $this->countNodes($content, '//div[contains(concat(\' \', normalize-space(@class), \' \'), \' uq-clamp \')]'), '二十行的說明要收合');
    }

    /**
     * 截止日寫在「期間限定」那個標籤本身（截至 MM/DD 限定價格），卡片與商品頁
     * 都一樣——這是 master 的做法，列表上看得到哪天結束才決定得了要不要現在買。
     *
     * 檔期過了 is_limited_offer 就是 false，標籤連同日期一起消失，不會剩一行
     * 日期孤零零掛在價格底下。
     */
    public function test_the_limited_offer_deadline_is_on_the_label_and_only_within_the_window(): void
    {
        $endsAt = now()->addDays(3);

        $this->seedProduct([
            'time_limited_begin' => now()->subDay(),
            'time_limited_end' => $endsAt,
        ]);

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            $this->countNodes(
                $content,
                '//*[contains(@class, "uq-price-status-item")][normalize-space()="截至 '.$endsAt->format('m/d').' 限定價格"]'
            ),
            '檔期內的標籤要寫出截止日'
        );

        HmallProduct::query()->update([
            'time_limited_begin' => now()->subDays(10),
            'time_limited_end' => now()->subDay(),
        ]);

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        // 整頁比對抓不到：頁尾的導覽本來就有「期間限定特價商品」這個入口
        $this->assertSame(
            0,
            $this->countNodes($content, '//*[contains(@class, "uq-price-status")]/*'),
            '檔期過了價格下面不該留下任何狀態行'
        );
    }

    public function test_a_product_without_a_description_renders_no_description_block(): void
    {
        $this->seedProduct();

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, $this->countNodes($content, '//*[contains(@class, "uq-description")]'));
        $this->assertSame(0, $this->countNodes($content, '//*[contains(@class, "uq-clamp-more")]'));
    }

    /**
     * 卡片整張的點擊區是那條覆蓋連結，不是包住整張卡片的 <a>。
     */
    public function test_the_card_is_clickable_through_an_overlay_link(): void
    {
        $this->seedProduct();

        $content = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        $this->assertGreaterThan(0, $this->countNodes($content, '//div[contains(@class, "uq-card")]/a[contains(concat(\' \', normalize-space(@class), \' \'), \' uq-card-link \')]'));
    }

    /**
     * v3 版把收藏鈕從圖片上搬到圖下的適穿列，圖片容器不該再有它；而且它
     * 要是真的 <button>，不是套了 icon 的 <a> 或 <div>。
     *
     * @dataProvider pagesWithCards
     */
    public function test_the_card_heart_is_a_button_outside_the_image(string $name, array $parameters): void
    {
        $this->seedProduct();

        $content = $this->get(route($name, $parameters))->assertOk()->getContent();

        $this->assertSame(
            0,
            $this->countNodes($content, '//*[contains(@class, "image")]//*[@data-favorite-card]'),
            "{$name} 的收藏鈕不該還在圖片容器裡"
        );
        $this->assertGreaterThan(
            0,
            $this->countNodes($content, '//button[@data-favorite-card]'),
            "{$name} 的收藏鈕要是真的 <button>"
        );
    }

    /**
     * 品名是卡片最主要的資訊，v3 版要看得到 .uq-card-name。
     *
     * @dataProvider pagesWithCards
     */
    public function test_a_card_shows_the_product_name(string $name, array $parameters): void
    {
        $this->seedProduct();

        $content = $this->get(route($name, $parameters))->assertOk()->getContent();

        $this->assertGreaterThan(0, $this->countNodes($content, '//*[contains(@class, "uq-card-name")]'));
    }

    /**
     * 狀態行 v3 版改成純文字，不該再掛 Tocas 的 .label 邊框——品牌角標例外，
     * 那是圖片右上角的既有設計，不是這次改的狀態行。
     */
    public function test_a_card_has_no_boxed_status_labels(): void
    {
        $this->seedProduct(['identity' => json_encode(['time_doptimal', 'concessional_rate'])]);

        $content = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            0,
            $this->countNodes(
                $content,
                '//*[contains(@class, "uq-card")]//*[contains(concat(" ", normalize-space(@class), " "), " label ")][not(contains(@class, "uq-card-brand"))]'
            ),
            '狀態行不該還有 Tocas 的 .label 邊框（品牌角標除外）'
        );
    }

    /**
     * 有原價且原價高於現價的商品，卡片要用 <del> 畫出來，不是純文字寫「原價」。
     *
     * 直接 render hmall-products.card，不透過分類頁那條路徑：這裡驗的是卡片
     * 模板本身收到 origin_price 時的邏輯，跟清單查詢實際查不查得到這個欄位
     * 是兩件事，分開驗。
     */
    public function test_a_card_with_an_origin_price_renders_a_del(): void
    {
        $hmallProduct = HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => 'UNIQLO',
            'name' => '測試商品',
            'code' => '450099',
            'product_code' => 'u990099',
            'sex' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 490,
            'origin_price' => 790,
        ]));

        $html = view('hmall-products.card', ['hmallProduct' => $hmallProduct])->render();

        $this->assertSame(1, $this->countNodes($html, '//del[contains(@class, "uq-card-origin")]'));
        $this->assertStringContainsString('790', $html);
    }

    /**
     * 商品頁的現價在有優惠時要換成優惠色，掛的是既有的 .uq-brand-text 工具
     * 類別，不是另外發明一個等效但驗不到的顏色規則。
     */
    public function test_the_product_page_price_uses_the_brand_text_color_when_discounted(): void
    {
        $this->seedProduct(['min_price' => 490, 'origin_price' => 790]);

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, $this->countNodes($content, '//del[contains(@class, "uq-price-origin")]'));
        $this->assertSame(
            1,
            $this->countNodes($content, '//span[contains(@class, "uq-price") and contains(@class, "uq-brand-text")]'),
            '有優惠時現價要掛 .uq-brand-text'
        );
    }

    /**
     * 麵包屑每一層的文字。
     *
     * @return array<int, string>
     */
    private function crumbs(string $html): array
    {
        $xpath = $this->xpath($html);
        $sections = $xpath->query('//nav[contains(@class, "uq-breadcrumb")]//*[contains(@class, "section")]');

        $labels = [];

        foreach ($sections as $section) {
            $labels[] = trim($section->textContent);
        }

        return $labels;
    }

    private function currentCrumb(string $html): ?string
    {
        $current = $this->xpath($html)->query('//nav[contains(@class, "uq-breadcrumb")]//*[@aria-current="page"]');

        return $current->length === 0 ? null : trim($current->item(0)->textContent);
    }

    private function countNodes(string $html, string $query): int
    {
        return $this->xpath($html)->query($query)->length;
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new \DOMXPath($dom);
    }

    /**
     * @param  array<string, mixed>  $overrides  這一個案例才需要的欄位（標籤用的 identity、說明用的 instruction）
     */
    private function seedProduct(array $overrides = []): void
    {
        $product = HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => '短袖上衣',
            'product_name' => '短袖上衣',
            'code' => '990001',
            'product_code' => 'u990001',
            'sex' => '女裝',
            'gender' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
            'lowest_record_price' => 990,
            'highest_record_price' => 990,
        ], $overrides)));

        foreach (['all_women' => '001', 'all_women-tops' => '001', 'all_women-tops-tshirt' => '001'] as $code => $sort) {
            $category = HmallCategory::where('brand', 'UNIQLO')->where('code', $code)->firstOrFail();
            $product->categories()->attach($category->id, ['sort' => $sort]);
        }
    }

    /**
     * 一行一句、總共二十行的說明。字數只有六十幾個，但排出來是二十行——行數的
     * 估算要照 <br> 拆才抓得到這種。
     */
    private function seedShortLinesProduct(): void
    {
        $this->seedProduct([
            'product_code' => 'u990002',
            'code' => '990002',
            'instruction' => implode('<br>', array_fill(0, 20, '短短一行')),
        ]);
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
}
