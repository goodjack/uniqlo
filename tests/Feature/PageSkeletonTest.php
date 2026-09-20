<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 全站共同骨架的驗收：麵包屑只出現在分類樹上的那兩頁、最後一層都是當頁，
 * 卡片上的收藏鈕不會變成連結的子孫、卡片標籤全部攤開來看得完，商品頁右欄
 * 不再靠小標題分段。
 *
 * 這些都是「改一頁很容易忘記另外六頁」的類型，所以用一組測試把七種頁面
 * 一起釘住，而不是各自散在各頁的測試裡。
 */
class PageSkeletonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 卡片與收藏列上的狀態標籤。外觀回到 Tocas 原生的
     * .ts.horizontal.basic.circular.label，一色一義的顏色寫在裡層 <span>
     * 的 style 上（見 hmall-products/partials/card-labels.blade.php）。
     *
     * 認 horizontal 加 circular 這個組合，不是只認 .label：頁首 slate 與圖片
     * 右上角的品牌角標也都是 .ts.label，只有狀態標籤是這個組合。
     */
    private const CARD_LABEL_XPATH = '//*[contains(@class, "horizontal")][contains(@class, "circular")][contains(@class, "label")]';

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
            $this->countNodes($content, self::CARD_LABEL_XPATH.'/span[normalize-space()="期間限定特價"]'),
            'identity 只有 APP 不該掛期間限定特價'
        );
        $this->assertSame(
            1,
            $this->countNodes($content, self::CARD_LABEL_XPATH.'/span[normalize-space()="APP 限定特價"]')
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

        $labels = $this->xpath($content)->query(self::CARD_LABEL_XPATH);

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
                '//a[contains(@class, "circular")][contains(@class, "label")]'.
                '[@href="'.route('lists.sale').'"][normalize-space()="特價商品"]'
            ),
            '特價商品要連回特價清單'
        );

        // icon 也照 master 補回來，一個標籤一個
        $this->assertSame(
            1,
            $this->countNodes(
                $content,
                '//a[contains(@class, "circular")][contains(@class, "label")]'.
                '[@href="'.route('lists.sale').'"]//i[contains(@class, "shopping")]'
            ),
            '標籤前面要有 icon'
        );

        $this->assertSame(
            0,
            $this->countNodes($content, '//a[contains(@class, "circular")][contains(@class, "label")][normalize-space()="修改褲長"]'),
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
            $this->countNodes($cardContent, self::CARD_LABEL_XPATH.'/span[normalize-space()="修改褲長"]'),
            '卡片不掛屬性標籤'
        );

        $productContent = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            $this->countNodes($productContent, self::CARD_LABEL_XPATH.'/span[normalize-space()="修改褲長"]'),
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

        $labels = $this->xpath($content)->query(self::CARD_LABEL_XPATH);

        // 四個：期間限定、特價、新款、合購。revision 是屬性標籤，只在商品頁
        $this->assertSame(4, $labels->length, '收藏清單不收合標籤');
        $this->assertSame(0, $this->countNodes($content, '//*[contains(@class, "uq-label-more")]'));
    }

    /**
     * 清單頁四個性別段一律都列，沒有商品的那段寫「沒有商品」——「男裝 0 件」
     * 也是資訊。v3 把 0 件的段整段藏起來，使用者會以為那一段不存在。
     */
    public function test_a_list_page_shows_every_gender_section(): void
    {
        $this->seedProduct(['identity' => json_encode(['concessional_rate'])]);

        $content = $this->get(route('lists.sale'))->assertOk()->getContent();

        foreach (['男裝', '女裝', '童裝', '嬰幼兒'] as $gender) {
            $this->assertSame(
                1,
                $this->countNodes($content, "//h2[@data-gender-heading][starts-with(normalize-space(), '{$gender}')]"),
                "{$gender} 那一段要在"
            );
        }

        // 只有女裝那件商品，其餘三段都是「沒有商品」
        $this->assertSame(3, substr_count($content, '<p>沒有商品</p>'));
    }

    /**
     * <title> 照 master 的句型：「1 件商品特價中」單獨一行就讀得懂。頁面上的
     * 標題維持「特價商品」加副標，那裡還有一行可以講件數與排序。
     */
    public function test_a_list_page_title_uses_the_master_phrasing(): void
    {
        $this->seedProduct(['identity' => json_encode(['concessional_rate'])]);

        $content = $this->get(route('lists.sale'))->assertOk()->getContent();

        $this->assertStringContainsString('<title>1 件商品特價中', $content);
        $this->assertSame(
            1,
            $this->countNodes($content, "//*[contains(@class, 'slate')]//*[normalize-space()='特價商品']"),
            '頁面上的標題維持新版的短名'
        );
    }

    /**
     * 商品頁右欄原本每一段各有一個小標題（標籤、所屬分類、商品資訊），三個標題
     * 加起來比它們標的內容還長，那三個 <h3> 拿掉了。
     *
     * 商品資訊與分類後來整段搬出右欄、變成 hero 底下的第一個章節（見
     * test_the_facts_live_in_their_own_section），所以「商品資訊」這四個字現在
     * 是章節標題 <h2>，不是右欄裡的小標題。
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
            $this->countNodes($content, '//*[contains(@class, "uq-facts")]//*[contains(@class, "item")]'),
            '商品資訊改用 Tocas 的 horizontal stackable list 呈現'
        );
    }

    /**
     * 商品資訊（適用對象、季節、網路商店編號）與分類那一行是屬性資料，不是站在
     * 價格前面決定要不要買的當下要看的東西，所以搬出 hero 右欄，跟商品實照、
     * 歷史價格一樣是往下讀的章節，並排在章節選單的第一項。
     *
     * hero 右欄只剩：標題、元資料列、價格與狀態行、CTA 列、分隔線、商品說明。
     */
    public function test_the_facts_live_in_their_own_section(): void
    {
        $this->seedProduct();

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            0,
            $this->countNodes($content, '//*[@id="comment"]//*[contains(@class, "uq-facts")]'),
            '商品資訊不留在 hero 右欄'
        );
        $this->assertSame(
            1,
            $this->countNodes(
                $content,
                '//*[@id="facts"]/following::*[contains(@class, "uq-facts")]'
            ),
            '商品資訊在自己的章節裡、排在錨點後面'
        );
        $this->assertSame(
            1,
            $this->countNodes($content, '//h2[contains(@class, "header")][normalize-space()="商品資訊"]'),
            '章節標題用跟其他章節同一種 Tocas 標題'
        );
        $this->assertSame(1, $this->countNodes($content, '//*[@id="facts"]'), '章節有錨點');
    }

    /**
     * 商品說明是這一頁的正文，住在 master 的 #comment 框裡。
     *
     * 那個框在桌機是固定 400px、超出自己捲（見 show.blade.php 的 @section('css')），
     * 這就是「描述短的時候上下也很平衡」的來源：右欄總高度不隨說明長短變動，
     * 價格與 CTA 固定落在圖片底部附近。所以說明長短都是同一種呈現，不再有自訂
     * 的「顯示更多」收合。
     *
     * 本機資料庫沒有這個欄位的內容（正式機有），所以自己塞一段進去。
     */
    public function test_the_description_sits_in_the_fixed_comment_box(): void
    {
        $this->seedProduct(['instruction' => str_repeat('這是一段夠長的商品說明文字。', 100)]);

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, $this->countNodes($content, '//*[@id="comment"]'), '說明在 #comment 框裡');
        $this->assertStringContainsString('這是一段夠長的商品說明文字。', $content);
        $this->assertSame(0, $this->countNodes($content, '//details'), '不再有自訂的顯示更多收合');
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
                self::CARD_LABEL_XPATH.'/span[normalize-space()="截至 '.$endsAt->format('m/d').' 限定價格"]'
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
            $this->countNodes($content, self::CARD_LABEL_XPATH),
            '檔期過了標題底下不該留下任何狀態標籤'
        );
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
     * 收藏鈕擺在卡片內容區、不疊在照片上，所以圖片容器不該有它；而且它要是
     * 真的 <button>，不是套了 icon 的 <a> 或 <div>。
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
     * 品名是卡片最主要的資訊。版面回到 Tocas 的 .ts.card 預設之後，品名是
     * .smaller.header，不再是自訂的 .uq-card-name。
     *
     * @dataProvider pagesWithCards
     */
    public function test_a_card_shows_the_product_name(string $name, array $parameters): void
    {
        $this->seedProduct();

        $content = $this->get(route($name, $parameters))->assertOk()->getContent();

        $this->assertGreaterThan(
            0,
            $this->countNodes($content, '//div[contains(@class, "header")][contains(@class, "smaller")]')
        );
    }

    /**
     * 狀態標籤用 Tocas 原生的 .ts.horizontal.basic.circular.label，跟 master
     * 一樣，不是自己寫一套純文字的狀態行。
     */
    public function test_a_card_uses_tocas_labels_for_its_status(): void
    {
        $this->seedProduct(['identity' => json_encode(['time_doptimal', 'concessional_rate'])]);

        $content = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        // 期間限定與特價兩顆，都要是 Tocas 的 horizontal basic circular label
        $this->assertSame(
            2,
            $this->countNodes(
                $content,
                '//*[contains(@class, "description")]/div[contains(@class, "ts")][contains(@class, "horizontal")]'.
                '[contains(@class, "basic")][contains(@class, "circular")][contains(@class, "label")]'
            )
        );
    }

    /**
     * 卡片不重複講同一件事：歷史區間已經把最高與最低寫出來了，不再另外放一條
     * 「原價 $XXX」的刪除線。官方的 origin_price 常常就等於歷史最高，兩行讀
     * 起來是同一個意思。
     *
     * 直接 render hmall-products.card，不透過分類頁那條路徑：這裡驗的是卡片
     * 模板本身的呈現，跟清單查詢查得到哪些欄位是兩件事，分開驗。
     */
    public function test_a_card_does_not_repeat_the_origin_price(): void
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
            'highest_record_price' => 790,
            'lowest_record_price' => 390,
        ]));

        $html = view('hmall-products.card', ['hmallProduct' => $hmallProduct])->render();

        $this->assertSame(0, $this->countNodes($html, '//del'), '卡片不該有原價刪除線');
        $this->assertStringNotContainsString('原價', $html);

        // 區間那一行還在：現價 490、歷史 790 – 390，而且現價高於歷史最低要染綠
        $range = $this->xpath($html)->query('//div[contains(@class, "sub")][contains(@class, "header")]');
        $this->assertSame(1, $range->length);
        $this->assertStringContainsString('790', $range->item(0)->textContent);
        $this->assertStringContainsString('390', $range->item(0)->textContent);
        // 2026-09 這輪把寫死的 #8BB96E（白底只有 2.27:1）加深到 --uq-new-text
        // token（跟 HmallProductPresenter::COLOR_NEW 同一個顏色，見 app.css）
        $this->assertStringContainsString('var(--uq-new-text)', $html, '現價還高於歷史最低時，最低價染綠');
    }

    /**
     * 商品頁的價格回到 master：分隔線底下一個 <h2>，只寫現價，沒有原價那一行。
     *
     * 原價跟卡片上的歷史區間是同一件事的兩種講法，站主判定重複；歷史價格另外
     * 有一整個章節（含圖表），比一行刪除線講得清楚。
     */
    public function test_the_product_page_price_is_a_plain_h2_without_an_origin_price(): void
    {
        $this->seedProduct(['min_price' => 490, 'origin_price' => 790]);

        $content = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, $this->countNodes($content, '//del'), '商品頁不該有原價刪除線');
        $this->assertSame(
            1,
            $this->countNodes($content, '//h2[normalize-space()="$490"]'),
            '現價是一個乾淨的 h2'
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
        $sections = $xpath->query('//nav[contains(@class, "breadcrumb")]//*[contains(@class, "section")]');

        $labels = [];

        foreach ($sections as $section) {
            $labels[] = trim($section->textContent);
        }

        return $labels;
    }

    private function currentCrumb(string $html): ?string
    {
        $current = $this->xpath($html)->query('//nav[contains(@class, "breadcrumb")]//*[@aria-current="page"]');

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
