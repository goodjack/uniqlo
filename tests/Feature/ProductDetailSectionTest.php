<?php

namespace Tests\Feature;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Enums\SocialPostStatus;
use App\Models\HmallProduct;
use App\Models\JapanProduct;
use App\Models\SocialPost;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
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

    public function test_official_videos_lead_the_photo_strip_and_wait_until_scrolled_to(): void
    {
        $this->seedProduct(['main_images' => $this->mainImages(2), 'videos' => ['/hmall/test/u990001/video/1000/2.mp4']]);

        $xpath = $this->productPage();

        $strip = $this->sectionXpath('photos').'//div[contains(@class, "uq-strip")]';
        $this->assertSame(1, $xpath->query($strip)->length);
        $videos = $xpath->query($strip.'/div[1]//video[@data-src]');
        $this->assertSame(1, $videos->length);
        $this->assertSame('https://www.uniqlo.com/tw/hmall/test/u990001/video/1000/2.mp4', $videos->item(0)->getAttribute('data-src'));
        $this->assertSame('none', $videos->item(0)->getAttribute('preload'));
        $this->assertFalse($videos->item(0)->hasAttribute('src'));
        $this->assertSame('商品實照', trim($xpath->query($this->sectionXpath('photos').'//h2')->item(0)->textContent));
    }

    public function test_japan_videos_and_photos_join_the_photo_strip_with_a_japan_label(): void
    {
        $product = $this->seedProduct(['main_images' => $this->mainImages(2), 'videos' => ['/hmall/test/u990001/video/1000/2.mp4']]);
        $this->seedJapanVideo($product, [['image' => 'https://image.uniqlo.com/UQ/ST3/jp/imagesgoods/990001/item/jpgoods_09_990001.jpg']]);

        $xpath = $this->productPage();

        // 順序：台灣影片 → 日本影片 → 台灣圖 → 日本圖；只有日本版的卡片標「日本版」
        $cards = iterator_to_array($xpath->query($this->sectionXpath('photos').'//div[contains(@class, "uq-strip")]/*'));
        $this->assertSame(
            [
                ['https://www.uniqlo.com/tw/hmall/test/u990001/video/1000/2.mp4', ''],
                [self::JAPAN_VIDEO, '日本版'],
                ['https://www.uniqlo.com/tw/hmall/test/u990001/main/other1/1000/2.jpg', ''],
                ['https://image.uniqlo.com/UQ/ST3/jp/imagesgoods/990001/item/jpgoods_09_990001.jpg', '日本版'],
            ],
            array_map(fn ($card) => [
                $xpath->query('.//video[@data-src]/@data-src | self::a/@href', $card)->item(0)->nodeValue,
                trim($xpath->query('.//*[contains(@class, "label")]', $card)->item(0)?->textContent ?? ''),
            ], $cards)
        );

        // 日本版段只留資訊（價格、前往日本官網），不放圖與影片
        $this->assertSame(0, $xpath->query($this->sectionXpath('japan').'//video')->length);
        $this->assertSame(0, $xpath->query($this->sectionXpath('japan').'//*[@data-lightbox]')->length);
        $this->assertSame(1, $xpath->query($this->sectionXpath('japan').'//a[@data-analytics-official="japan"]')->length);
    }

    public function test_a_product_with_only_japan_videos_still_gets_a_photo_section(): void
    {
        $product = $this->seedProduct(['main_images' => $this->mainImages(1)]);
        $this->seedJapanVideo($product);

        $xpath = $this->productPage();

        $this->assertSame(1, $xpath->query($this->sectionXpath('photos').'//video[@data-src]')->length);
        $this->assertSame('日本版', trim($xpath->query($this->sectionXpath('photos').'//*[contains(@class, "label")]')->item(0)->textContent));
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
            $xpath->query($this->sectionXpath('photos').'//video[@data-src]')->item(0)->getAttribute('data-src')
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
        $this->assertStringContainsString('aspect-ratio: 1000 / 1000', $image->getAttribute('style'));
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

        $html = (string) $view;
        $captionClass = ltrim($this->xpath($html)->query('//a[@data-lightbox]')->item(0)->getAttribute('data-description'), '.');
        $this->assertStringStartsWith('uq-caption-', $captionClass);

        // 說明放在 <template> 裡、外層又是 <a>，整頁解析會把巢狀連結拆掉，所以只拿 template 的內容來解析
        $this->assertSame(1, preg_match('~<template class="'.preg_quote($captionClass, '~').'">(.*?)</template>~s', $html, $template));
        $caption = $this->xpath($template[1]);
        $link = $caption->query('//a')->item(0);

        $this->assertSame('https://www.uniqlo.com/tw/stylehint/1', $link->getAttribute('href'));
        $this->assertSame('_blank', $link->getAttribute('target'));
        $this->assertContains('noopener', explode(' ', $link->getAttribute('rel')));
        $this->assertSame('<img src=x onerror=alert(1)>小明', trim($link->textContent));
        $this->assertSame(0, $caption->query('//img')->length, '名稱裡的標籤不能變成真的元素');
        $view->assertDontSee('<img src=x', false);
    }

    public function test_the_specs_fold_material_care_origin_and_store_code_without_the_rating_distribution(): void
    {
        $this->seedProduct([
            'origin_place' => '柬埔寨製',
            'rating_levels' => [16, 1, 2, 0, 0],
            'material_care' => [
                ['title' => '商品材質', 'content' => "棉 100%\n<script>x</script>"],
                ['title' => '洗滌說明', 'content' => '洗衣機/弱水流。不可烘乾。'],
            ],
        ]);

        $html = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $specs = $this->sectionXpath('specs');

        $details = $xpath->query($specs.'//details')->item(0);
        $this->assertFalse($details->hasAttribute('open'), '規格預設收合');
        $this->assertStringContainsString('商品材質、洗滌方式等 5 項', $xpath->query('./summary', $details)->item(0)->textContent);

        $rows = [];
        foreach ($xpath->query($specs.'//table[contains(@class, "uq-facts")]/tbody/tr') as $row) {
            $rows[trim($xpath->query('./td[1]', $row)->item(0)->textContent)] = array_map(
                fn ($item) => trim($item->textContent),
                iterator_to_array($xpath->query('./td[2]//*[contains(@class, "item")]', $row)) ?: [$xpath->query('./td[2]', $row)->item(0)]
            );
        }
        // 材質洗滌在前、一行一件事、兩個品牌的叫法統一；網路商店編號排最後
        $this->assertSame(['商品材質', '洗滌方式', '適用對象', '產地', '網路商店編號'], array_keys($rows));
        $this->assertSame(['棉 100%', '<script>x</script>'], $rows['商品材質']);
        $this->assertSame(['洗衣機/弱水流。', '不可烘乾。'], $rows['洗滌方式']);
        $this->assertSame(['柬埔寨製'], $rows['產地']);
        $this->assertStringNotContainsString('<script>x</script>', $html);

        // 官網評價先不顯示
        $this->assertStringNotContainsString('評價分布', $html);
        $this->assertSame(0, $xpath->query('//div[contains(@class, "progress")]')->length);
    }

    public function test_feature_images_open_in_the_lightbox_and_the_full_description_stays_on_top(): void
    {
        $this->seedProduct([
            'features' => [['image' => '/hmall/test/u990001/features/1.jpg', 'text' => '<b>具備保暖功能</b>']],
        ], ['instruction' => '具備保暖功能。<br>- 寬鬆版型。']);

        $html = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $features = $this->sectionXpath('features');

        // 跟商品實照同一種卡片欄數（電腦四張、手機兩張），圖在卡片裡、說明在圖下方
        $feature = $xpath->query($features.'//div[contains(@class, "doubling four cards")]/div[contains(@class, "card")]/a[@data-lightbox="features"]')->item(0);
        $this->assertSame('https://www.uniqlo.com/tw/hmall/test/u990001/features/1.jpg', $feature->getAttribute('href'));
        $this->assertSame('image', $feature->getAttribute('data-type'));
        $this->assertSame(
            '<b>具備保暖功能</b>',
            trim($xpath->query($features.'//div[contains(@class, "card")]//div[contains(@class, "description")]')->item(0)->textContent)
        );
        $this->assertStringContainsString('&lt;b&gt;具備保暖功能&lt;/b&gt;', $html);

        // 說明全文留在主區，跟特點圖說重複也照樣顯示
        $this->assertSame('具備保暖功能。- 寬鬆版型。', trim($xpath->query('//*[@id="product_hero"]//*[@id="comment"]')->item(0)->textContent));
    }

    public function test_a_product_without_feature_images_has_no_features_section(): void
    {
        $this->seedProduct(null, ['instruction' => '只有說明的商品。']);

        $xpath = $this->productPage();

        $this->assertSame(0, $xpath->query('//*[@id="features"]')->length);
        $this->assertSame('只有說明的商品。', trim($xpath->query('//*[@id="comment"]')->item(0)->textContent));
    }

    public function test_sections_follow_the_agreed_order_and_the_menu_lists_them_the_same_way(): void
    {
        $this->seedProductWithEverySection();

        $xpath = $this->productPage();

        $this->assertSame(
            ['features', 'photos', 'social', 'style-hints', 'specs', 'price-history', 'japan'],
            array_map(fn ($heading) => $heading->getAttribute('data-section-view'), iterator_to_array($xpath->query('//h2[@data-section-view]')))
        );
        $menu = iterator_to_array($xpath->query('//*[@id="product_menu"]/a'));
        $this->assertSame(['特點', '實照', '社群熱推', '穿搭', '規格', '歷史價格', '日本版'], array_map(fn ($item) => trim($item->textContent), $menu));
        foreach ($menu as $item) {
            $this->assertSame(1, $xpath->query('//*[@id="'.substr($item->getAttribute('href'), 1).'"]')->length, $item->getAttribute('href'));
        }
    }

    public function test_every_section_heading_comes_from_the_shared_header_component(): void
    {
        // 商品頁的段標題一律用 x-section-header，不在頁面裡另外手寫（主區的價格不是段標題）
        foreach (glob(resource_path('views/hmall-products/{,partials/}*.blade.php'), GLOB_BRACE) as $view) {
            $this->assertDoesNotMatchRegularExpression('/<h2[^>]*class=/', file_get_contents($view), basename($view));
        }

        $this->seedProductWithEverySection();

        $xpath = $this->productPage();

        // 每段標題長一樣、沒有底線；右側按鈕不管是連結還是按鈕都同一種樣式
        $headings = iterator_to_array($xpath->query('//h2[@data-section-view]'));
        $this->assertCount(7, $headings);
        foreach ($headings as $heading) {
            $this->assertSame('ts big header uq-section-header', $heading->getAttribute('class'), $heading->getAttribute('data-section-view'));
        }
        $actions = iterator_to_array($xpath->query('//h2[@data-section-view]/*[contains(@class, "button")]'));
        $kinds = array_unique(array_map(fn ($action) => $action->nodeName, $actions));
        sort($kinds);
        $this->assertSame(['a', 'button'], $kinds, 'StyleHint 的連結與社群熱推的按鈕都要驗到');
        // 圖示可選：給了圖示的多 labeled icon，其餘樣式完全一樣
        $this->assertCount(1, array_unique(array_map(fn ($action) => str_replace(' labeled icon', '', $action->getAttribute('class')), $actions)));
        $icons = [];
        foreach ($actions as $action) {
            $icons[$action->parentNode->getAttribute('data-section-view')] = $xpath->query('./i/@class', $action)->item(0)?->nodeValue;
        }
        $this->assertSame(['social' => 'play icon', 'style-hints' => 'camera retro icon'], $icons);
        foreach ($actions as $action) {
            $this->assertSame(
                $xpath->query('./i', $action)->length === 1,
                str_contains($action->getAttribute('class'), 'labeled icon'),
                trim($action->textContent)
            );
        }
        $this->assertSame(0, $xpath->query('//*[contains(@class, "uq-social-head")]')->length);
    }

    public function test_the_section_header_button_shows_an_icon_only_when_one_is_given(): void
    {
        $plain = $this->xpath(Blade::render('<x-section-header section="x" action-label="看全部" action-href="/all">標題</x-section-header>'));
        $withIcon = $this->xpath(Blade::render('<x-section-header section="x" action-label="看全部" action-href="/all" action-icon="play">標題</x-section-header>'));

        $this->assertSame('ts right floated button uq-header-action', $plain->query('//a')->item(0)->getAttribute('class'));
        $this->assertSame(0, $plain->query('//a/i')->length);
        $this->assertSame('ts right floated labeled icon button uq-header-action', $withIcon->query('//a')->item(0)->getAttribute('class'));
        $this->assertSame('play icon', $withIcon->query('//a/i')->item(0)->getAttribute('class'));
    }

    public function test_each_section_reports_when_it_is_seen_and_how_it_is_opened(): void
    {
        $product = $this->seedProduct(['material_care' => [['title' => '商品材質', 'content' => '棉 100%']]]);
        $this->seedJapanVideo($product);

        $xpath = $this->productPage();

        // 歷史價格與日本版除了標題，價格內容進入畫面時另外算一次
        $this->assertSame(
            ['price-history', 'japan'],
            array_map(fn ($node) => $node->getAttribute('data-section-view'), iterator_to_array($xpath->query('//*[@data-section-exposure="content"]')))
        );
        $this->assertSame('specs', $xpath->query('//details[@data-analytics-details]')->item(0)->getAttribute('data-analytics-details'));
        $this->assertSame(
            ['hero', 'japan'],
            array_map(fn ($link) => $link->getAttribute('data-analytics-official'), iterator_to_array($xpath->query('//a[@data-analytics-official]')))
        );
    }

    public function test_the_structured_data_lists_every_image_but_not_the_rating_distribution(): void
    {
        // 則數挑一個不會跟頁面其他數字撞到的值，才能確定它沒有以任何形式出現在結構化資料裡
        $this->seedProduct(['main_images' => $this->mainImages(3), 'rating_levels' => [7351, 0, 0, 0, 0]]);

        $html = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))->assertOk()->getContent();
        $jsonLd = collect($this->xpath($html)->query('//script[@type="application/ld+json"]'))
            ->map(fn ($node) => json_decode($node->textContent, true))
            ->firstWhere('@type', 'Product');

        $this->assertSame([
            'https://www.uniqlo.com/tw/hmall/test/u990001/main/first/1000/1.jpg',
            'https://www.uniqlo.com/tw/hmall/test/u990001/main/other1/1000/2.jpg',
            'https://www.uniqlo.com/tw/hmall/test/u990001/main/other2/1000/3.jpg',
        ], $jsonLd['image']);
        // 評價分布目前不顯示（資料照存），頁面上與結構化資料裡都不該出現
        $this->assertStringNotContainsString('7351', $html);
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

    private function seedJapanVideo(HmallProduct $product, array $mainImages = []): void
    {
        JapanProduct::unguarded(fn () => JapanProduct::create([
            'brand' => 'UNIQLO',
            'product_id' => 'E990001-000',
            'l1Id' => $product->code,
            'main_images' => $mainImages,
            'sub_images' => [],
            'sub_videos' => [['video' => self::JAPAN_VIDEO]],
        ]));
    }

    /** 每一段都有內容的商品：特點、商品實照、社群熱推、StyleHint、規格、日本版 */
    private function seedProductWithEverySection(): void
    {
        $product = $this->seedProduct([
            'main_images' => $this->mainImages(2),
            'features' => [['image' => '/hmall/test/u990001/features/1.jpg', 'text' => '具備保暖功能']],
            'material_care' => [['title' => '商品材質', 'content' => '棉 100%']],
        ]);
        DB::table('style_hints')->insert(['id' => 1, 'outfit_id' => '16693239', 'country' => 'tw', 'style_image_url' => 'https://api.fastretailing.com/ugc/v1/uq/jp/SR_IMAGES/ugc_stylehint_uq_jp_photo_1_2043193']);
        DB::table('style_hint_items')->insert(['style_hint_id' => 1, 'code' => '990001', 'original_product_id' => '990001-09']);
        $this->seedJapanVideo($product);
        SocialPost::create([
            'notion_page_id' => 'page-1',
            'notion_created_at' => now()->subDay(),
            'notion_edited_at' => now(),
            'source_url' => 'https://www.threads.com/@meta/post/1',
            'canonical_url' => 'https://www.threads.com/@meta/post/1',
            'platform' => SocialPlatform::Threads,
            'kind' => SocialPostKind::Post,
            'author' => 'Meta',
            'text' => '內文',
            'status' => SocialPostStatus::Ok,
        ])->codes()->create(['code' => '990001']);

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
