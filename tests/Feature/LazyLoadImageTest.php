<?php

namespace Tests\Feature;

use App\Models\Style;
use App\Presenters\ProductPresenter;
use Tests\TestCase;

/**
 * 圖片直接寫真網址，交給瀏覽器原生的 loading="lazy" 延後載入。
 * 不再靠頁尾程式把 data-src 搬到 src：程式還沒跑（或跑不起來）時圖片整片空白。
 */
class LazyLoadImageTest extends TestCase
{
    private const SRC = 'https://im.uniqlo.com/global-cms/spa/res/goods_00_990001.jpg';

    public function test_deferred_image_points_straight_at_the_real_url(): void
    {
        $html = (string) $this->blade(
            '<x-lazy-load-image src="'.self::SRC.'" alt="短袖上衣" width="2" height="3" class="ts image" />'
        );

        $this->assertStringContainsString('src="'.self::SRC.'"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('aspect-ratio: 2 / 3; object-fit: cover;', $html);
        $this->assertStringContainsString('class="ts image"', $html);
        $this->assertStringNotContainsString('data-src', $html);
        $this->assertStringNotContainsString('data:image/svg+xml', $html);
        $this->assertStringNotContainsString('lazyload', $html);
    }

    public function test_priority_image_loads_right_away(): void
    {
        $html = (string) $this->blade(
            '<x-lazy-load-image src="'.self::SRC.'" alt="短袖上衣" :priority="true" />'
        );

        $this->assertStringContainsString('src="'.self::SRC.'"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringNotContainsString('loading="lazy"', $html);
    }

    public function test_old_product_page_style_images_point_straight_at_the_real_url(): void
    {
        $style = (new Style)->forceFill(['type' => 'old', 'detail_url' => 'https://example.com/style', 'image_url' => self::SRC, 'image_path' => 'style/1']);
        $card = (new ProductPresenter)->getStyleCards([$style], [])[0];
        $html = (string) $this->blade(
            '<x-image-card :link="$card[\'link\']" :image-url="$card[\'imageUrl\']" :large-image-url="$card[\'largeImageUrl\']" :alt="$card[\'alt\']" />',
            ['card' => $card],
        );

        $this->assertStringContainsString('<img src="'.self::SRC.'" alt="精選穿搭 Style 1" loading="lazy"', $html);
        $this->assertStringNotContainsString('data-src', $html);
        $this->assertStringNotContainsString('lazyload', $html);
    }

    public function test_pages_no_longer_ship_the_image_swapping_script(): void
    {
        $html = $this->get(route('favorites.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('lazysizes', $html);
        $this->assertStringNotContainsString('dataset.src', $html);
    }
}
