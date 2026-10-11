<?php

namespace Tests\Feature;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_product_json_ld_names_only_the_product_and_keeps_special_characters(): void
    {
        $this->seedProduct(['name' => 'ZO&FRIENDS 短袖上衣']);

        $product = $this->jsonLdOfType($this->productPage(), 'Product');

        $this->assertSame('女裝 ZO&FRIENDS 短袖上衣', $product['name']);
    }

    /**
     * 每天都會重抓，priceValidUntil 填抓取當天，Google 存下的版本很快就變成過去的日期。
     */
    public function test_the_product_offer_has_a_currency_and_no_validity_date(): void
    {
        $this->seedProduct(['lowest_record_price' => 590, 'highest_record_price' => 1290]);

        $offers = $this->jsonLdOfType($this->productPage(), 'Product')['offers'];

        $this->assertSame('TWD', $offers['priceCurrency']);
        $this->assertArrayNotHasKey('priceValidUntil', $offers);
        $this->assertEquals(590, $offers['lowPrice']);
        $this->assertEquals(1290, $offers['highPrice']);
    }

    public function test_the_product_rating_is_typed(): void
    {
        $this->seedProduct(['score' => 4.5, 'evaluation_count' => 12]);

        $rating = $this->jsonLdOfType($this->productPage(), 'Product')['aggregateRating'];

        $this->assertSame('AggregateRating', $rating['@type']);
        $this->assertEquals(4.5, $rating['ratingValue']);
    }

    public function test_product_pages_are_open_graph_products_and_other_pages_are_websites(): void
    {
        $this->seedProduct();

        $this->assertSame('product', $this->meta($this->productPage(), 'og:type'));
        $this->assertSame('website', $this->meta($this->get(route('home'))->getContent(), 'og:type'));
        $this->assertSame('UQ 搜尋', $this->meta($this->productPage(), 'og:site_name'));
        $this->assertSame('zh_TW', $this->meta($this->productPage(), 'og:locale'));
    }

    /**
     * 只是文字的中間層（品牌頂層）沒有網址，Google 不接受，要跳過；最後一層是當頁，可以沒有網址。
     */
    public function test_the_breadcrumb_list_skips_crumbs_without_a_link(): void
    {
        $product = $this->seedProduct();
        $this->attachCategoryTrail($product);

        $items = $this->jsonLdOfType($this->productPage(), 'BreadcrumbList')['itemListElement'];

        $this->assertSame(['首頁', '商品分類', '上衣類', 'T恤', '女裝 短袖上衣'], array_column($items, 'name'));
        $this->assertSame([1, 2, 3, 4, 5], array_column($items, 'position'));

        foreach (array_slice($items, 0, -1) as $item) {
            $this->assertNotEmpty($item['item'], "{$item['name']} 缺網址");
        }
    }

    public function test_the_home_page_describes_the_site(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame('UQ 搜尋｜UNIQLO 與 GU 價格查詢比價', $this->title($content));
        $this->assertNotEmpty($this->meta($content, 'description'));
        $this->assertSame(route('home'), $this->xpath($content)->query('//link[@rel="canonical"]')->item(0)?->getAttribute('href'));

        $website = $this->jsonLdOfType($content, 'WebSite');
        $this->assertSame('UQ 搜尋', $website['name']);
        $this->assertSame(route('home'), $website['url']);
    }

    public function test_other_pages_keep_the_site_name_after_the_page_title(): void
    {
        $content = $this->get(route('categories.index'))->assertOk()->getContent();

        $this->assertSame('商品分類 | UQ 搜尋', $this->title($content));
    }

    public function test_the_google_search_page_is_not_indexed(): void
    {
        $content = $this->get(route('search.google-cse', ['query' => '外套']))->assertOk()->getContent();

        $this->assertSame('noindex, follow', $this->meta($content, 'robots'));
    }

    public function test_a_style_hint_page_without_photos_is_not_indexed(): void
    {
        $this->seedProduct();

        $content = $this->get(route('uniqlo-style-hints.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();

        $this->assertSame('noindex, follow', $this->meta($content, 'robots'));
    }

    /**
     * @dataProvider pagesWithATitle
     */
    public function test_every_page_has_exactly_one_h1(string $name, array $parameters): void
    {
        $this->seedProduct();

        $content = $this->get(route($name, $parameters))->assertOk()->getContent();

        $this->assertSame(1, $this->xpath($content)->query('//h1')->length, "{$name} 應該剛好有一個 h1");
    }

    public static function pagesWithATitle(): array
    {
        return [
            '首頁' => ['home', []],
            '清單頁' => ['lists.sale', []],
            '分類總覽' => ['categories.index', []],
            '搜尋結果' => ['search.show', ['query' => '外套']],
            '收藏' => ['favorites.index', []],
            '商品頁' => ['uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']],
            'Google 搜尋頁' => ['search.google-cse', []],
            '更新日誌' => ['pages.changelog', []],
        ];
    }

    /**
     * 官方會把共用的其他編號串在名稱裡，對外標題要另外接上自己的編號，搜尋結果與分享
     * 才看得出是哪一件；頁面上的 h1 與麵包屑不接，編號已經寫在 h1 下一行。
     */
    public function test_outward_product_titles_end_with_the_products_own_code(): void
    {
        $this->seedProduct(['name' => '繭型牛仔褲482280 / 490633', 'code' => '487973']);

        $content = $this->productPage();
        $expected = '女裝 繭型牛仔褲482280 / 490633 487973';

        $this->assertSame("{$expected} | UQ 搜尋", $this->title($content));
        $this->assertSame("{$expected} | UQ 搜尋", $this->meta($content, 'og:title'));
        $this->assertSame("{$expected} | UQ 搜尋", $this->meta($content, 'twitter:title'));
        $this->assertSame("{$expected} | UNIQLO 比價 | UQ 搜尋", $this->meta($content, 'share:text'));
        // h1 的第一段文字是商品名，編號在後面的副標
        $this->assertSame('女裝 繭型牛仔褲482280 / 490633', trim($this->xpath($content)->query('//h1')->item(0)->firstChild->textContent));
    }

    public function test_style_hint_page_titles_end_with_the_products_own_code(): void
    {
        $this->seedProduct(['name' => '方形太陽眼鏡', 'code' => '481637']);

        $content = $this->get(route('uniqlo-style-hints.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();
        $expected = '女裝 方形太陽眼鏡 481637 的 StyleHint 網友穿搭靈感';

        $this->assertSame("{$expected} | UQ 搜尋", $this->title($content));
        $this->assertSame("{$expected} | UQ 搜尋", $this->meta($content, 'og:title'));
        $this->assertSame("{$expected} | UQ 搜尋", $this->meta($content, 'twitter:title'));
        $this->assertStringNotContainsString('481637', $this->xpath($content)->query('//h1')->item(0)->textContent);
    }

    public function test_the_not_found_page_has_a_chinese_title_and_one_h1(): void
    {
        $content = $this->get('/this-page-does-not-exist')->assertNotFound()->getContent();

        $this->assertSame('找不到頁面 | UQ 搜尋', $this->title($content));
        $this->assertSame(1, $this->xpath($content)->query('//h1')->length);
    }

    private function productPage(): string
    {
        return $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))
            ->assertOk()
            ->getContent();
    }

    private function attachCategoryTrail(HmallProduct $product): void
    {
        $levels = [
            ['all_women', '女裝', null, CategoryLevel::Top],
            ['all_women-tops', '上衣類', 'all_women', CategoryLevel::One],
            ['all_women-tops-tshirt', 'T恤', 'all_women-tops', CategoryLevel::Two],
        ];

        foreach ($levels as [$code, $name, $parent, $level]) {
            $category = HmallCategory::create([
                'brand' => 'UNIQLO',
                'code' => $code,
                'name' => $name,
                'parent_code' => $parent,
                'level' => $level->value,
            ]);
            $product->categories()->attach($category->id, ['sort' => '001']);
        }
    }

    private function seedProduct(array $overrides = []): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
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
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jsonLd(string $html): array
    {
        return collect($this->xpath($html)->query('//script[@type="application/ld+json"]'))
            ->map(fn ($node) => json_decode($node->textContent, true, flags: JSON_THROW_ON_ERROR))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLdOfType(string $html, string $type): array
    {
        $matches = array_values(array_filter($this->jsonLd($html), fn ($data) => ($data['@type'] ?? null) === $type));

        $this->assertCount(1, $matches, "頁面上應該剛好有一份 {$type}");

        return $matches[0];
    }

    private function meta(string $html, string $property): ?string
    {
        $node = $this->xpath($html)->query("//meta[@property='{$property}' or @name='{$property}']")->item(0);

        return $node?->getAttribute('content');
    }

    /**
     * 跟瀏覽器的 document.title 一樣，頭尾去空白、中間連續空白併成一個
     */
    private function title(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->xpath($html)->query('//title')->item(0)->textContent));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
