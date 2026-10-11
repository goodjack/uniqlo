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

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
