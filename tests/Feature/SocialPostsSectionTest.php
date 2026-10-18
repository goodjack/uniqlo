<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialPostsSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => 'UNIQLO',
            'name' => '寬褲',
            'product_name' => '寬褲',
            'code' => '479123',
            'product_code' => 'u479123',
            'sex' => '女裝',
            'gender' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 990,
            'lowest_record_price' => 990,
            'highest_record_price' => 990,
        ]));
    }

    private function seedPost(string $author, array $attributes = [], array $codes = ['479123'], ?int $start = null): SocialPost
    {
        static $n = 0;
        $n++;

        $post = SocialPost::create(array_merge([
            'notion_page_id' => "page-{$n}",
            'notion_created_at' => now()->subDays(10 - $n),
            'notion_edited_at' => now(),
            'source_url' => "https://www.threads.com/@a/post/{$n}",
            'canonical_url' => "https://www.threads.com/@a/post/{$n}",
            'platform' => 'threads',
            'kind' => '貼文',
            'author' => $author,
            'text' => '內文',
            'status' => 'ok',
        ], $attributes));
        $post->codes()->createMany(array_map(fn ($code) => ['code' => $code, 'start_seconds' => $start], $codes));

        return $post;
    }

    private function productPage(): string
    {
        return $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u479123']))
            ->assertOk()
            ->getContent();
    }

    public function test_section_is_hidden_without_posts(): void
    {
        $this->seedPost('別的商品', codes: ['999999']);
        $this->seedPost('抓取失敗', ['status' => 'failed']);

        $html = $this->productPage();

        $this->assertStringNotContainsString('社群熱推', $html);
        $this->assertStringNotContainsString('social-posts.js', $html);
        $this->assertStringNotContainsString('glightbox', $html);
    }

    public function test_sorted_posts_come_first_then_newest(): void
    {
        $this->seedPost('舊的沒排序');
        $this->seedPost('排序二', ['sort' => 2]);
        $this->seedPost('新的沒排序');
        $this->seedPost('排序一', ['sort' => 1]);

        $html = $this->productPage();

        $this->assertStringContainsString('社群熱推', $html);
        $this->assertStringContainsString('social-posts.js', $html);
        $this->assertStringContainsString('逛全部 4 則', $html);
        preg_match_all('~class="uq-social-author">([^<]+)<~', $html, $m);
        $this->assertSame(['排序一', '排序二', '新的沒排序', '舊的沒排序'], $m[1]);
    }

    public function test_youtube_card_carries_video_id_and_uses_the_official_thumbnail(): void
    {
        $this->seedPost('質男日著', [
            'platform' => 'youtube',
            'kind' => 'Shorts',
            'youtube_id' => 'T4Q6DY8xJgQ',
        ]);

        $html = $this->productPage();

        $this->assertStringContainsString('data-youtube-id="T4Q6DY8xJgQ" data-vertical="1"', $html);
        // Shorts 用直式縮圖，沒有就退回一般縮圖
        $this->assertStringContainsString('data-src="https://i.ytimg.com/vi/T4Q6DY8xJgQ/oardefault.jpg"', $html);
        $this->assertStringContainsString('https://i.ytimg.com/vi/T4Q6DY8xJgQ/hqdefault.jpg', $html);
        $this->assertStringContainsString('aria-label="YouTube：質男日著"', $html);
    }

    public function test_youtube_card_starts_where_the_video_talks_about_this_product(): void
    {
        $this->seedPost('質男日著', ['platform' => 'youtube', 'kind' => '影片', 'youtube_id' => 'VUBxkGogbp0'], start: 83);
        $this->seedPost('Tony', start: 125);

        $html = $this->productPage();

        $this->assertStringContainsString('data-start="83"', $html);
        $this->assertMatchesRegularExpression('~uq-social-badge">\s*<i class="play icon" aria-hidden="true"></i>\s*1:23\s*</span>~', $html);
        // IG、Threads 的官方嵌入不能指定開始時間，填了也不顯示
        $this->assertStringNotContainsString('2:05', $html);
        $this->assertSame(1, substr_count($html, 'data-start='));
    }

    public function test_preview_image_goes_through_our_own_url_and_card_shows_the_handle(): void
    {
        $post = $this->seedPost('Tony Wang (@haku_ou1015)', ['cover_url' => 'https://scontent.cdninstagram.com/p.jpg?oe=6AD5FF99']);
        $this->seedPost('只有文字', ['text' => '沒有圖的貼文']);

        $html = $this->productPage();

        $this->assertStringContainsString('data-src="'.e($post->image_url).'"', $html);
        $this->assertStringContainsString('/social-img/'.$post->id.'.jpg', $html);
        $this->assertStringNotContainsString('cdninstagram.com', $html);
        $this->assertStringContainsString('class="uq-social-author">@haku_ou1015<', $html);
        $this->assertMatchesRegularExpression('~class="uq-social-quote"\s*>.*?沒有圖的貼文~s', $html);
    }

    public function test_x_video_card_shows_the_relayed_preview_and_a_play_badge(): void
    {
        $video = $this->seedPost('美国之音中文网', [
            'platform' => 'x',
            'kind' => '影片',
            'canonical_url' => 'https://x.com/VOAChinese/status/1',
            'cover_url' => 'https://pbs.twimg.com/amplify_video_thumb/1/img/a?format=webp&name=large',
        ]);
        $this->seedPost('純文字', ['platform' => 'x', 'canonical_url' => 'https://x.com/a/status/2', 'text' => '只有字的推文']);

        $html = $this->productPage();

        $this->assertStringContainsString('data-src="'.e($video->image_url).'"', $html);
        $this->assertStringNotContainsString('pbs.twimg.com', $html);
        $this->assertSame(1, substr_count($html, 'uq-social-badge'));
        $this->assertMatchesRegularExpression('~class="uq-social-quote"\s*>.*?只有字的推文~s', $html);
        // 卡片上只有 YouTube 預覽播放，X 點開才用官方嵌入
        $this->assertStringNotContainsString('data-youtube-id', $html);
    }

    public function test_post_text_is_escaped(): void
    {
        $this->seedPost('<img src=x onerror=alert(1)>', ['text' => '<script>alert(1)</script>']);

        $html = $this->productPage();

        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_section_comes_right_after_the_price_area_and_first_in_the_menu(): void
    {
        $this->seedPost('Tony');

        $html = $this->productPage();

        $this->assertLessThan(strpos($html, '>商品資訊</h2>'), strpos($html, '>社群熱推</h2>'));
        preg_match('~id="product_menu".*?</div>~s', $html, $menu);
        $this->assertLessThan(strpos($menu[0], '商品資訊'), strpos($menu[0], '社群熱推'));
    }

    public function test_platform_embed_is_built_from_the_url_only(): void
    {
        $this->seedPost('Tony', [
            'canonical_url' => 'https://www.threads.com/@haku_ou1015/post/DIqyoWbyeky',
            'text' => '只在卡片上出現的內文',
        ]);

        $html = $this->productPage();
        preg_match('~<template data-embed="0">(.*?)</template>~s', $html, $m);

        [$embed, $fallback] = explode('uq-social-fallback', $m[1]);

        $this->assertStringContainsString('data-text-post-permalink="https://www.threads.com/t/DIqyoWbyeky"', $embed);
        $this->assertStringNotContainsString('只在卡片上出現的內文', $embed);
        // 平台嵌入被擋時才顯示的備用卡片，用的是自己存的內容
        $this->assertStringContainsString('只在卡片上出現的內文', $fallback);
    }
}
