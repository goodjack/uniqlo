<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use App\Models\Product;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 舊系統商品頁與 StyleHint 穿搭頁的放大圖，跟新商品頁用同一套 GLightbox 與說明寫法。
 */
class PhotoLightboxTest extends TestCase
{
    use RefreshDatabase;

    private const HTML_TEXT = '<b>小明</b>';

    public function test_style_hint_captions_are_plain_text(): void
    {
        $this->seedHmallProduct();
        DB::table('style_hints')->insert([
            'id' => 1, 'outfit_id' => '16693239', 'country' => 'jp', 'user_name' => self::HTML_TEXT,
            'style_image_url' => 'https://api.fastretailing.com/ugc/v1/uq/jp/SR_IMAGES/ugc_stylehint_uq_jp_photo_1_2043193',
        ]);
        DB::table('style_hint_items')->insert(['style_hint_id' => 1, 'code' => '990001', 'original_product_id' => 'p1']);

        $html = $this->get(route('uniqlo-style-hints.show', ['uniqlo_product_code' => 'u990001']))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertStringNotContainsString(self::HTML_TEXT, $html);
        $this->assertSame(0, $xpath->query('//*[@data-title]')->length);
        $this->assertStringContainsString('&lt;b&gt;小明&lt;/b&gt;', $html);

        $link = $xpath->query('//a[@data-lightbox]')->item(0);
        $caption = $xpath->query('//template[contains(@class, "'.ltrim($link->getAttribute('data-description'), '.').'")]')->item(0);
        $this->assertStringContainsString(self::HTML_TEXT, $caption->textContent);
        $this->assertSame(0, $xpath->query('.//b', $caption)->length);
        // 縮圖的替代文字也只轉義一次：解析後就是原本的名稱，不是 &lt;b&gt;
        $this->assertStringContainsString(self::HTML_TEXT, $xpath->query('.//img', $link)->item(0)->getAttribute('alt'));

        $this->assertOpensAsImages($xpath, 1);
        $this->assertUsesTheSharedLightbox($html);
    }

    public function test_old_product_page_photos_open_as_images_with_plain_text_captions(): void
    {
        Product::unguarded(fn () => Product::create([
            'id' => '4199990001',
            'name' => '<b>男裝 圓領T恤</b>',
            'price' => 390,
            'main_image_url' => 'https://im.uniqlo.com/images/tw/uq/pc/goods/4199990001/item/09_4199990001.jpg',
            'sub_images' => json_encode(['sub1']),
            'colors' => json_encode([['code' => '09', 'name' => '<i>黑色</i>']]),
        ]));
        DB::table('styles')->insert([
            'id' => 's1', 'dpt_id' => 'd1', 'image_path' => 'style/s1',
            'image_url' => 'https://im.uniqlo.com/style/s1-l.jpg', 'detail_url' => 'https://www.uniqlo.com/tw/style/s1',
        ]);
        // 新版穿搭的大圖網址規則不同，掛到舊系統商品也要組對
        DB::table('styles')->insert([
            'id' => 's2', 'type' => 'uq', 'dpt_id' => 'd1', 'image_path' => 'stylingbook/s2',
        ]);
        DB::table('product_style')->insert([
            ['product_id' => '4199990001', 'style_id' => 's1'],
            ['product_id' => '4199990001', 'style_id' => 's2'],
        ]);

        $html = $this->get(route('products.show', ['product' => '4199990001']))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertStringNotContainsString('<b>男裝', $html);
        $this->assertStringNotContainsString('<i>黑色', $html);
        $this->assertSame(0, $xpath->query('//*[@data-title]')->length);
        $this->assertStringContainsString('<template class="uq-caption-hero">&lt;b&gt;男裝 圓領T恤&lt;/b&gt;</template>', $html);

        // 舊系統的縮圖照原圖比例，不裁切；替代文字只轉義一次
        $cardImages = iterator_to_array($xpath->query('//a[contains(@class, "card")][@data-lightbox]//img'));
        $this->assertCount(4, $cardImages);
        foreach ($cardImages as $image) {
            $this->assertFalse($image->hasAttribute('style'), $image->getAttribute('src'));
        }
        $this->assertSame('商品顏色 09 <i>黑色</i>', end($cardImages)->getAttribute('alt'));

        // 穿搭大圖網址跟 Style 模型同一套規則
        $this->assertSame([
            'https://im.uniqlo.com/style/style/s1-xxxl.jpg',
            'https://api.fastretailing.com/ugc/v1/uq/gl/OFFICIAL_IMAGES/stylingbook/s2_r-1000-1333',
        ], array_map(
            fn ($link) => $link->getAttribute('href'),
            iterator_to_array($xpath->query('//a[@data-lightbox="styles"]'))
        ));

        // 主圖、穿搭兩張、實照一張、顏色一張；穿搭自成一本，主圖與實照連著翻
        $this->assertOpensAsImages($xpath, 5);
        $this->assertSame(['image', 'styles', 'styles', 'image', 'image'], array_map(
            fn ($link) => $link->getAttribute('data-lightbox'),
            iterator_to_array($xpath->query('//a[@data-lightbox]'))
        ));
        $this->assertUsesTheSharedLightbox($html);
    }

    private function assertOpensAsImages(DOMXPath $xpath, int $expected): void
    {
        $links = iterator_to_array($xpath->query('//a[@data-lightbox]'));

        $this->assertCount($expected, $links);
        foreach ($links as $link) {
            $this->assertSame('image', $link->getAttribute('data-type'), $link->getAttribute('href'));
            $this->assertStringStartsWith('https://', $link->getAttribute('href'));
            $this->assertNotSame('', $link->getAttribute('data-description'));
        }
    }

    private function assertUsesTheSharedLightbox(string $html): void
    {
        $this->assertStringContainsString('glightbox.min.js', $html);
        $this->assertStringContainsString('product-photos.js', $html);
        $this->assertStringNotContainsString('lightbox2', $html);
        $this->assertStringNotContainsString('jquery', strtolower($html));
    }

    private function seedHmallProduct(): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => 'UNIQLO',
            'name' => '短袖上衣',
            'product_name' => '短袖上衣',
            'code' => '990001',
            'product_code' => 'u990001',
            'sex' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
            'lowest_record_price' => 990,
            'highest_record_price' => 990,
        ]));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
