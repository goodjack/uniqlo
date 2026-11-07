<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use App\Models\JapanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfficialLinkTest extends TestCase
{
    use RefreshDatabase;

    public static function taiwanLinks(): array
    {
        return [
            'UNIQLO' => ['UNIQLO', 'uniqlo-hmall-products.show', 'https://www.uniqlo.com/tw/zh_TW/product-detail.html?productCode=u990001'],
            'GU' => ['GU', 'gu-hmall-products.show', 'https://www.gu-global.com/tw/zh_TW/product-detail.html?productCode=u990001'],
        ];
    }

    #[DataProvider('taiwanLinks')]
    public function test_the_product_page_links_to_the_taiwan_official_page(string $brand, string $route, string $url): void
    {
        $this->seedProduct($brand);

        $this->get(route($route, 'u990001'))
            ->assertOk()
            ->assertSee('href="'.$url.'"', false);
    }

    public static function japanLinks(): array
    {
        return [
            'UNIQLO 單一價格' => ['UNIQLO', 'uniqlo-hmall-products.show', [1990], 'https://www.uniqlo.com/jp/ja/products/E470077-000'],
            'UNIQLO 多種價格' => ['UNIQLO', 'uniqlo-hmall-products.show', [1990, 2990], 'https://www.uniqlo.com/jp/ja/search?q=990001'],
            'GU 單一價格' => ['GU', 'gu-hmall-products.show', [1990], 'https://www.gu-global.com/jp/ja/products/E470077-000'],
            'GU 多種價格' => ['GU', 'gu-hmall-products.show', [1990, 2990], 'https://www.gu-global.com/jp/ja/search?q=990001'],
        ];
    }

    #[DataProvider('japanLinks')]
    public function test_the_japan_section_links_to_the_japan_official_page(string $brand, string $route, array $prices, string $url): void
    {
        $this->seedProduct($brand);
        JapanProduct::unguarded(fn () => JapanProduct::create([
            'brand' => $brand,
            'l1Id' => '990001',
            'product_id' => 'E470077-000',
            'name' => 'テスト',
            'prices' => $prices,
            'main_images' => [],
            'sub_images' => [],
            'sub_videos' => [],
        ]));

        $this->get(route($route, 'u990001'))
            ->assertOk()
            ->assertSee('href="'.$url.'"', false);
    }

    private function seedProduct(string $brand): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => $brand,
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
    }
}
