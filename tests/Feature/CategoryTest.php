<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        // 第三輪 UI 把子分類從 pill 改成文字連結列，不再是實心 pill
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
        // 子分類要列出來，讓使用者往下鑽
        $response->assertSee('T恤');
        // 第三輪 UI 把工具列的子分類從 pill 改成文字連結列
        $response->assertSee('uq-cat-list');
        $response->assertDontSee('uq-pill-small');
    }

    /**
     * 分類主檔只增不減，商品全部下架的分類仍留在表裡，不能讓它變成空頁面。
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
     * 兩家的分類 code 各成一套（UNIQLO 是 all_women-tops、GU 是 women_all），
     * 名稱又會撞，所以總覽要標出這是誰的分類。
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
     * 第三輪 UI 修正：章節選單的 sticky 外層要站在頁面 container 外面，
     * 自己再包一層 container，白底跟底線才是滿版而不是只跨中間那欄。
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
     * 陣列型的 query 參數同樣不該炸掉頁面。篩選表單原本用 request()->except()
     * 把所有其他參數塞進 hidden input，array 丟給 Blade 轉字串就是 500。
     */
    public function test_an_array_query_string_does_not_break_the_category_page(): void
    {
        $this->attachProduct($this->createProduct(['brand' => 'UNIQLO', 'name' => '短袖上衣']), 'all_women-tops');

        $response = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']).'?ref[]=x');

        $response->assertOk();
        $response->assertSee('短袖上衣');
    }

    /**
     * 篩選之後可能一件都不剩。原本這種情況只剩一片空白，使用者看不出是篩太緊
     * 還是頁面壞掉。文案跟清單頁一致。
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

    /**
     * 官方在該分類內的排序權重決定顯示順序，出來就跟官網一致。
     */
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
     * 第三輪 UI 把分類頁的分頁換成跟 style-hints 頁共用的 markup
     * （Tocas 的 .ts.icon.button 上一頁／頁碼／下一頁），不再是自訂的三顆
     * pill。這裡塞超過一頁（24 件）的商品，確認新元件真的接上去了。
     */
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
     * 從 SEO 直接進分類頁的人需要知道自己在整棵樹的哪裡。
     * 頂層沒有自己的頁面，所以只當文字不做連結。
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
     * 分類的身分是品牌加 code，不是 code 本身。
     *
     * 兩家目前只有頂層的 ALL 同名，但沒有任何保證未來不會撞——撞到時父分類與
     * 子分類都不能串到另一家。這個測試直接建兩個同 code 的分類來釘住。
     */
    public function test_a_category_never_borrows_the_other_brands_tree(): void
    {
        $this->createCategory('all_top', 'UNIQLO 全部商品', null, CategoryLevel::Top, 'UNIQLO');
        $this->createCategory('all_top', 'GU 全部商品', null, CategoryLevel::Top, 'GU');

        // 兩家各有一個 code 完全相同的大類，各自掛在自己的頂層底下
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
     * 品項頁不列出官方的錨點細分。
     *
     * 那一層開不出頁面（findPageable 只認 levelOne 與 levelTwo），列出來就是
     * 一整排 404。本機實測 /categories/uniqlo/all_women-tops-t-shirts 上有 13 個
     * 這種連結，真實資料裡受影響的品項頁有 246 個。
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

    /**
     * 大類頁仍然要列出它的品項，那一層開得出頁面，是往下鑽的正常路徑。
     */
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

        /*
         * 只看分類那一行。整頁比對會被麵包屑干擾：麵包屑走的是主分類那一條
         * 路徑，本來就會連到其中一個「T恤」，跟這裡要驗的去重是兩件事。
         */
        $links = $this->categoryLinksInLine($content);

        $this->assertNotEmpty($links, '商品頁應該列出所屬分類');

        $linksToWomen = in_array('all_women-tops-tshirt', $links, true);
        $linksToMen = in_array('all_men-tops-tshirt', $links, true);

        $this->assertTrue($linksToWomen || $linksToMen, '商品頁應該列出所屬分類');
        $this->assertFalse($linksToWomen && $linksToMen, '同名的分類只該出現一次');
    }

    /**
     * 抓出商品頁「分類」那一行裡的分類連結，只回傳分類 code。
     *
     * 原本是用「所屬分類」那個小標題定位。第二輪 UI 把三個小標題（標籤、
     * 所屬分類、商品資訊）都拿掉了，改由那一行自己的 class 定位。
     *
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
