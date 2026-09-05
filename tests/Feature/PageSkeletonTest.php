<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 全站共同骨架的驗收：麵包屑只出現在分類樹上的那兩頁、最後一層都是當頁，
 * 卡片上的收藏鈕不會變成連結的子孫。
 *
 * 這兩件事都是「改一頁很容易忘記另外六頁」的類型，所以用一組測試把七種頁面
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
     * 卡片整張的點擊區是那條覆蓋連結，不是包住整張卡片的 <a>。
     */
    public function test_the_card_is_clickable_through_an_overlay_link(): void
    {
        $this->seedProduct();

        $content = $this->get(route('categories.show', ['brand' => 'uniqlo', 'code' => 'all_women-tops']))
            ->assertOk()
            ->getContent();

        $this->assertGreaterThan(0, $this->countNodes($content, '//div[contains(@class, "uq-card")]/a[@class="uq-card-link"]'));
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

    private function seedProduct(): void
    {
        $product = HmallProduct::unguarded(fn () => HmallProduct::create([
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
        ]));

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
