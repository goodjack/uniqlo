<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use App\Models\JapanProduct;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductDetailSectionTest extends TestCase
{
    use RefreshDatabase;

    private const JAPAN_VIDEO = 'https://image.uniqlo.com/UQ/ST3/jp/imagesgoods/990001/subvideo/jpgoods_990001_sub1_video.mp4';

    public function test_the_first_main_image_stays_on_top_and_the_rest_join_the_product_photos(): void
    {
        $this->seedProduct(['main_images' => $this->mainImages(4)], ['color_nums' => json_encode(['COL09'])]);

        $xpath = $this->productPage();

        $hero = $xpath->query('//*[@id="product_hero"]//a[@data-lightbox]')->item(0);
        $this->assertSame('https://www.uniqlo.com/tw/hmall/test/u990001/main/first/1000/1.jpg', $hero->getAttribute('href'));
        $heroImage = $xpath->query('.//img', $hero)->item(0);
        $this->assertSame('https://www.uniqlo.com/tw/hmall/test/u990001/main/first/561/1.jpg', $heroImage->getAttribute('src'));
        $this->assertSame('high', $heroImage->getAttribute('fetchpriority'));

        $photos = array_map(
            fn ($card) => $card->getAttribute('href'),
            iterator_to_array($xpath->query($this->sectionXpath('photos').'//a[@data-lightbox]'))
        );
        $this->assertSame([
            'https://www.uniqlo.com/tw/hmall/test/u990001/main/other1/1000/2.jpg',
            'https://www.uniqlo.com/tw/hmall/test/u990001/main/other2/1000/3.jpg',
            'https://www.uniqlo.com/tw/hmall/test/u990001/main/other3/1000/4.jpg',
            'https://www.uniqlo.com/tw/hmall/test/u990001/sku/561/COL09.jpg',
        ], $photos);
    }

    public function test_a_product_without_details_still_shows_its_first_picture_only(): void
    {
        $this->seedProduct();

        $xpath = $this->productPage();

        $hero = $xpath->query('//*[@id="product_hero"]//a[@data-lightbox]')->item(0);
        $this->assertSame('https://www.uniqlo.com/tw/hmall/test/u990001/main/first/561/1.jpg', $hero->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//*[@id="photos"]')->length);
    }

    public function test_videos_go_to_the_video_section_and_wait_until_scrolled_to(): void
    {
        $this->seedProduct(['main_images' => $this->mainImages(1), 'videos' => ['/hmall/test/u990001/video/1000/2.mp4']]);

        $xpath = $this->productPage();

        $videos = $xpath->query($this->sectionXpath('videos').'//video[@data-src]');
        $this->assertSame(1, $videos->length);
        $this->assertSame('https://www.uniqlo.com/tw/hmall/test/u990001/video/1000/2.mp4', $videos->item(0)->getAttribute('data-src'));
        $this->assertSame('none', $videos->item(0)->getAttribute('preload'));
        $this->assertFalse($videos->item(0)->hasAttribute('src'));
        $this->assertStringNotContainsString('日本版', $xpath->query($this->sectionXpath('videos').'//h2')->item(0)->textContent);
    }

    public function test_japan_videos_are_labelled_when_official_videos_are_also_shown(): void
    {
        $product = $this->seedProduct(['main_images' => $this->mainImages(1), 'videos' => ['/hmall/test/u990001/video/1000/2.mp4']]);
        $this->seedJapanVideo($product);

        $xpath = $this->productPage();

        $cards = iterator_to_array($xpath->query($this->sectionXpath('videos').'//div[contains(@class, "uq-video")]'));
        $this->assertCount(2, $cards);
        $this->assertSame(0, $xpath->query('.//*[contains(@class, "label")]', $cards[0])->length);
        $this->assertSame('日本版', trim($xpath->query('.//*[contains(@class, "label")]', $cards[1])->item(0)->textContent));
        $this->assertSame(self::JAPAN_VIDEO, $xpath->query('.//video[@data-src]', $cards[1])->item(0)->getAttribute('data-src'));
    }

    public function test_only_japan_videos_keep_the_label_in_the_heading(): void
    {
        $product = $this->seedProduct(['main_images' => $this->mainImages(1)]);
        $this->seedJapanVideo($product);

        $xpath = $this->productPage();

        $this->assertStringContainsString('日本版', $xpath->query($this->sectionXpath('videos').'//h2')->item(0)->textContent);
        $this->assertSame(0, $xpath->query($this->sectionXpath('videos').'//div[contains(@class, "uq-video")]//*[contains(@class, "label")]')->length);
    }

    public function test_the_top_picture_gets_an_escaped_caption_in_the_lightbox(): void
    {
        $this->seedProduct(['main_images' => $this->mainImages(1)], ['name' => '<b>短袖上衣</b>']);

        $html = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))->assertOk()->getContent();
        $hero = $this->xpath($html)->query('//*[@id="product_hero"]//a[@data-lightbox]')->item(0);

        $this->assertSame('.uq-caption-hero', $hero->getAttribute('data-description'));
        $this->assertStringContainsString('<template class="uq-caption-hero">女裝 &lt;b&gt;短袖上衣&lt;/b&gt;</template>', $html);
    }

    public function test_gu_images_and_videos_use_the_gu_site(): void
    {
        $this->seedProduct(
            ['main_images' => $this->mainImages(2, 'u990002'), 'videos' => ['/hmall/test/u990002/video/1000/intro.mp4']],
            ['brand' => 'GU', 'product_code' => 'u990002'],
        );

        $xpath = $this->xpath($this->get(route('gu-hmall-products.show', ['gu_product_code' => 'u990002']))->assertOk()->getContent());

        $this->assertSame(
            'https://www.gu-global.com/tw/hmall/test/u990002/main/first/1000/1.jpg',
            $xpath->query('//*[@id="product_hero"]//a[@data-lightbox]')->item(0)->getAttribute('href')
        );
        $this->assertSame(
            'https://www.gu-global.com/tw/hmall/test/u990002/main/other1/1000/2.jpg',
            $xpath->query($this->sectionXpath('photos').'//a[@data-lightbox]')->item(0)->getAttribute('href')
        );
        $this->assertSame(
            'https://www.gu-global.com/tw/hmall/test/u990002/video/1000/intro.mp4',
            $xpath->query($this->sectionXpath('videos').'//video[@data-src]')->item(0)->getAttribute('data-src')
        );
    }

    public function test_photos_open_in_a_single_swipeable_lightbox_library(): void
    {
        $this->seedProduct(['main_images' => $this->mainImages(2)]);

        $html = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))->assertOk()->getContent();

        $this->assertStringContainsString('glightbox.min.js', $html);
        $this->assertStringContainsString('product-photos.js', $html);
        $this->assertStringNotContainsString('lightbox2', $html);
    }

    public function test_every_photo_opens_as_an_image_even_when_its_large_url_has_no_extension(): void
    {
        $this->seedProduct(['main_images' => $this->mainImages(2)], ['color_nums' => json_encode(['COL09'])]);
        DB::table('style_hints')->insert(['id' => 1, 'outfit_id' => '16693239', 'country' => 'tw', 'style_image_url' => 'https://api.fastretailing.com/ugc/v1/uq/jp/SR_IMAGES/ugc_stylehint_uq_jp_photo_1_2043193']);
        DB::table('style_hint_items')->insert(['style_hint_id' => 1, 'code' => '990001', 'original_product_id' => '990001-09']);

        $xpath = $this->productPage();

        $styleHint = $xpath->query($this->sectionXpath('style-hints').'//a[@data-lightbox]')->item(0);
        $this->assertSame('https://api.fastretailing.com/ugc/v1/uq/jp/SR_IMAGES/ugc_stylehint_uq_jp_photo_1_2043193_r-1000-1333', $styleHint->getAttribute('href'));

        $links = iterator_to_array($xpath->query('//a[@data-lightbox]'));
        $this->assertGreaterThanOrEqual(4, count($links));
        foreach ($links as $link) {
            $this->assertSame('image', $link->getAttribute('data-type'), $link->getAttribute('href'));
            $this->assertStringStartsWith('https://', $link->getAttribute('href'));
        }
    }

    public function test_feature_images_reserve_a_square_before_they_load(): void
    {
        $this->seedProduct(['features' => [['image' => '/hmall/test/u990001/features/1.jpg', 'text' => '具備保暖功能']]]);

        $image = $this->productPage()->query('//a[@data-lightbox="features"]//img')->item(0);

        $this->assertSame('lazy', $image->getAttribute('loading'));
        $this->assertNotSame('', $image->getAttribute('width'));
        $this->assertSame($image->getAttribute('width'), $image->getAttribute('height'));
    }

    public function test_reduced_motion_turns_off_every_lightbox_animation_on_the_page(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        preg_match('/@media \(prefers-reduced-motion: reduce\) \{\s*((?:body \.g[a-zA-Z]+,?\s*)+)\{\s*animation-duration: \.01ms;/', $css, $matches);

        $this->assertNotEmpty($matches, '找不到減少動態效果時縮短放大圖動畫的規則');
        // 商品照片用淡入淡出與滑動，社群熱推用預設的放大縮小
        foreach (['gfadeIn', 'gfadeOut', 'gzoomIn', 'gzoomOut', 'gslideInRight', 'gslideInLeft', 'gslideOutLeft', 'gslideOutRight'] as $animation) {
            $this->assertStringContainsString(".{$animation}", $matches[1]);
        }
    }

    public function test_photo_card_captions_are_escaped_for_the_lightbox(): void
    {
        $view = $this->blade(
            '<x-image-card link="https://www.uniqlo.com/tw/stylehint/1" imageUrl="https://a/1.jpg" largeImageUrl="https://a/1.jpg" :alt="$alt" />',
            ['alt' => '<img src=x onerror=alert(1)>小明'],
        );

        $view->assertSee('data-description=".uq-caption-', false);
        $view->assertSee('<a href="https://www.uniqlo.com/tw/stylehint/1" target="_blank" rel="nofollow noopener">&lt;img src=x onerror=alert(1)&gt;小明</a>', false);
        $view->assertDontSee('<img src=x', false);
    }

    public function test_origin_material_care_rating_distribution_and_features_are_in_the_product_facts(): void
    {
        $this->seedProduct([
            'origin_place' => '柬埔寨製',
            'rating_levels' => [16, 1, 2, 0, 0],
            'features' => [['image' => '/hmall/test/u990001/features/1.jpg', 'text' => '<b>具備保暖功能</b>']],
            'material_care' => [['title' => '商品材質', 'content' => "棉 100%\n<script>x</script>"]],
        ]);

        $html = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $facts = $this->sectionXpath('facts');

        $rows = [];
        foreach ($xpath->query($facts.'//table[contains(@class, "uq-facts")]/tbody/tr') as $row) {
            $rows[trim($xpath->query('./td[1]', $row)->item(0)->textContent)] = $xpath->query('./td[2]', $row)->item(0);
        }
        $this->assertSame('柬埔寨製', trim($rows['產地']->textContent));
        $this->assertStringContainsString('棉 100%', $rows['商品材質']->textContent);
        // 0 則的星等不畫長條
        $this->assertSame(3, $xpath->query('.//div[contains(@class, "progress")]/div[@class="bar"]', $rows['評價分布'])->length);
        $this->assertStringContainsString('16 則', $rows['評價分布']->textContent);

        // 特點圖點了用同一套放大圖；說明文字不論內容都照樣放在圖下方
        $feature = $xpath->query($facts.'//a[@data-lightbox="features"]')->item(0);
        $this->assertSame('https://www.uniqlo.com/tw/hmall/test/u990001/features/1.jpg', $feature->getAttribute('href'));
        $this->assertSame('image', $feature->getAttribute('data-type'));
        $this->assertSame(
            '<b>具備保暖功能</b>',
            trim($xpath->query($facts.'//div[contains(@class, "items")]//div[contains(@class, "description")]')->item(0)->textContent)
        );
        $this->assertStringContainsString('&lt;b&gt;具備保暖功能&lt;/b&gt;', $html);
        $this->assertStringContainsString('棉 100%<br />', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    public function test_the_structured_data_lists_every_image_but_not_the_rating_distribution(): void
    {
        $this->seedProduct(['main_images' => $this->mainImages(3), 'rating_levels' => [16, 1, 2, 0, 0]]);

        $html = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))->getContent();
        $jsonLd = collect($this->xpath($html)->query('//script[@type="application/ld+json"]'))
            ->map(fn ($node) => json_decode($node->textContent, true))
            ->firstWhere('@type', 'Product');

        $this->assertCount(3, $jsonLd['image']);
        $this->assertStringNotContainsString('"16"', json_encode($jsonLd));
        $this->assertArrayNotHasKey('review', $jsonLd);
    }

    private function mainImages(int $count, string $productCode = 'u990001'): array
    {
        return array_map(fn (int $i) => [
            'medium' => $i === 1 ? "/hmall/test/{$productCode}/main/first/561/1.jpg" : "/hmall/test/{$productCode}/main/other".($i - 1)."/561/{$i}.jpg",
            'large' => $i === 1 ? "/hmall/test/{$productCode}/main/first/1000/1.jpg" : "/hmall/test/{$productCode}/main/other".($i - 1)."/1000/{$i}.jpg",
        ], range(1, $count));
    }

    private function seedProduct(?array $detail = null, array $attributes = []): HmallProduct
    {
        $product = HmallProduct::unguarded(fn () => HmallProduct::create($attributes + [
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
            'main_first_pic' => '/hmall/test/u990001/main/first/561/1.jpg',
        ]));

        if ($detail !== null) {
            $product->detail()->create($detail + ['fetched_at' => now()]);
        }

        return $product;
    }

    private function seedJapanVideo(HmallProduct $product): void
    {
        JapanProduct::unguarded(fn () => JapanProduct::create([
            'brand' => 'UNIQLO',
            'product_id' => 'E990001-000',
            'l1Id' => $product->code,
            'main_images' => [],
            'sub_images' => [],
            'sub_videos' => [['video' => self::JAPAN_VIDEO]],
        ]));
    }

    /** 章節錨點所在的那一段 */
    private function sectionXpath(string $anchor): string
    {
        return "//*[@id='{$anchor}']/ancestor::div[contains(@class, 'segment')][1]";
    }

    private function productPage(): DOMXPath
    {
        return $this->xpath($this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))->assertOk()->getContent());
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
