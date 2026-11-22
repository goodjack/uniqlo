<?php

namespace Tests\Feature;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Enums\SocialPostStatus;
use App\Models\HmallProduct;
use App\Models\SocialPost;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ParsesHtml;
use Tests\TestCase;

class SocialPostsSectionTest extends TestCase
{
    use ParsesHtml;
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
            'source_url' => "https://www.threads.com/@meta/post/{$n}",
            'canonical_url' => "https://www.threads.com/@meta/post/{$n}",
            'platform' => SocialPlatform::Threads,
            'kind' => SocialPostKind::Post,
            'author' => $author,
            'text' => '內文',
            'status' => SocialPostStatus::Ok,
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
        $this->seedPost('抓取失敗', ['status' => SocialPostStatus::Failed]);

        $html = $this->productPage();

        $this->assertStringNotContainsString('社群熱推', $html);
        $this->assertStringNotContainsString('social-posts.js', $html);
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
        $this->assertSame(['排序一', '排序二', '新的沒排序', '舊的沒排序'], $this->authors($this->xpath($html)));
    }

    public function test_same_post_listed_twice_shows_the_row_notion_keeps(): void
    {
        $url = 'https://www.threads.com/@meta/post/DDzbnVKx57R';
        $this->seedPost('較早建立的那列', ['canonical_url' => $url, 'sort' => 2]);
        $this->seedPost('較晚建立的那列', ['canonical_url' => $url, 'sort' => 1]);
        $this->seedPost('另一篇', ['sort' => 3]);

        $html = $this->productPage();
        $xpath = $this->xpath($html);

        // Notion 清單裡同一篇貼了兩次又合併不了時，商品頁只放一張，而且是 Notion 上沒標重複的那列（最早建立的）
        $this->assertSame(['較早建立的那列', '另一篇'], $this->authors($xpath));
        $this->assertStringNotContainsString('較晚建立的那列', $html);
        // 點開用的嵌入也跟卡片一一對應，不會多一份
        $this->assertSame(['0', '1'], array_map(
            fn ($template) => $template->getAttribute('data-embed'),
            iterator_to_array($xpath->query('//template[@data-embed]'))
        ));
        $this->assertSame(['0', '1'], array_map(fn ($card) => $card->getAttribute('data-index'), $this->cards($xpath)));
    }

    public function test_posts_without_a_canonical_url_are_not_merged_into_one(): void
    {
        $this->seedPost('第一則', ['canonical_url' => null]);
        $this->seedPost('第二則', ['canonical_url' => null]);

        $this->assertCount(2, $this->cards($this->xpath($this->productPage())));
    }

    public function test_youtube_card_carries_video_id_and_uses_the_official_thumbnail(): void
    {
        $this->seedPost('範例頻道', [
            'platform' => SocialPlatform::Youtube,
            'kind' => SocialPostKind::Short,
            'youtube_id' => 'M7lc1UVf-VE',
        ]);

        $xpath = $this->xpath($this->productPage());
        $card = $this->cards($xpath)[0];
        $image = $xpath->query('.//img', $card)->item(0);

        $this->assertSame('M7lc1UVf-VE', $card->getAttribute('data-youtube-id'));
        $this->assertSame('1', $card->getAttribute('data-vertical'));
        // Shorts 用直式縮圖，沒有就退回一般縮圖
        $this->assertSame('https://i.ytimg.com/vi/M7lc1UVf-VE/oardefault.jpg', $image->getAttribute('src'));
        $this->assertStringContainsString('https://i.ytimg.com/vi/M7lc1UVf-VE/hqdefault.jpg', $image->getAttribute('onerror'));
        $this->assertSame('YouTube：範例頻道', $xpath->query('.//*[contains(@class, "uq-social-title")]', $card)->item(0)->getAttribute('aria-label'));
    }

    public function test_youtube_card_starts_where_the_video_talks_about_this_product(): void
    {
        $this->seedPost('範例頻道', ['platform' => SocialPlatform::Youtube, 'kind' => SocialPostKind::Video, 'youtube_id' => '7lCDEYXw3mM'], start: 83);
        $this->seedPost('Meta', start: 125);

        $html = $this->productPage();
        $xpath = $this->xpath($html);
        $youtube = $this->cardBy($xpath, '範例頻道');
        $threads = $this->cardBy($xpath, 'Meta');

        $this->assertSame('83', $youtube->getAttribute('data-start'));
        $badge = $xpath->query('.//*[contains(@class, "uq-social-badge")]', $youtube)->item(0);
        $this->assertSame('1:23', trim($badge->textContent));
        $this->assertSame(1, $xpath->query('.//i[contains(@class, "play")]', $badge)->length);
        // IG、Threads 的官方嵌入不能指定開始時間，填了也不顯示
        $this->assertFalse($threads->hasAttribute('data-start'));
        $this->assertStringNotContainsString('2:05', $html);
    }

    public function test_preview_image_goes_through_our_own_url_and_card_shows_the_handle(): void
    {
        $post = $this->seedPost('Meta (@meta)', ['cover_url' => 'https://scontent.cdninstagram.com/p.jpg?oe=6AD5FF99']);
        $this->seedPost('只有文字', ['text' => '沒有圖的貼文']);

        $html = $this->productPage();
        $xpath = $this->xpath($html);
        $withImage = $this->cardBy($xpath, '@meta');
        $textOnly = $this->cardBy($xpath, '只有文字');

        $this->assertSame($post->image_url, $xpath->query('.//img', $withImage)->item(0)->getAttribute('src'));
        $this->assertStringContainsString('/social-img/'.$post->id.'.jpg', $post->image_url);
        $this->assertStringNotContainsString('cdninstagram.com', $html);
        $this->assertVisibleQuote($xpath, $textOnly, '沒有圖的貼文');
    }

    public function test_x_video_card_shows_the_relayed_preview_and_a_play_badge(): void
    {
        $video = $this->seedPost('US Department of the Interior', [
            'platform' => SocialPlatform::X,
            'kind' => SocialPostKind::Video,
            'canonical_url' => 'https://x.com/Interior/status/1',
            'cover_url' => 'https://pbs.twimg.com/amplify_video_thumb/1/img/a?format=webp&name=large',
        ]);
        $this->seedPost('純文字', ['platform' => SocialPlatform::X, 'canonical_url' => 'https://x.com/Interior/status/2', 'text' => '只有字的推文']);

        $html = $this->productPage();
        $xpath = $this->xpath($html);
        $videoCard = $this->cardBy($xpath, 'US Department of the Interior');
        $textOnly = $this->cardBy($xpath, '純文字');

        $this->assertSame($video->image_url, $xpath->query('.//img', $videoCard)->item(0)->getAttribute('src'));
        $this->assertStringNotContainsString('pbs.twimg.com', $html);
        $this->assertSame(1, $xpath->query('.//*[contains(@class, "uq-social-badge")]', $videoCard)->length);
        $this->assertSame(0, $xpath->query('.//*[contains(@class, "uq-social-badge")]', $textOnly)->length);
        $this->assertVisibleQuote($xpath, $textOnly, '只有字的推文');
        // 卡片上只有 YouTube 預覽播放，X 點開才用官方嵌入
        $this->assertSame(0, $xpath->query('//*[@data-youtube-id]')->length);
    }

    public function test_post_text_is_escaped(): void
    {
        $this->seedPost('<img src=x onerror=alert(1)>', ['text' => '<script>alert(1)</script>']);

        $html = $this->productPage();
        $xpath = $this->xpath($html);

        // 文字要照原樣顯示出來，而不是被吃掉或變成真的標籤
        $card = $this->cards($xpath)[0];
        $this->assertSame('<script>alert(1)</script>', trim($xpath->query('.//*[contains(@class, "uq-social-title")]', $card)->item(0)->textContent));
        $this->assertStringContainsString('<img src=x onerror=alert(1)>', $xpath->query('.//*[contains(@class, "uq-social-title")]', $card)->item(0)->getAttribute('aria-label'));
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_section_comes_right_after_the_price_area_and_first_in_the_menu(): void
    {
        $this->seedPost('Meta');

        $html = $this->productPage();
        $xpath = $this->xpath($html);

        $headings = array_map(fn ($h2) => trim($h2->textContent), iterator_to_array($xpath->query('//h2')));
        $this->assertContains('社群熱推', $headings);
        $this->assertContains('商品資訊', $headings);
        $this->assertLessThan(array_search('商品資訊', $headings, true), array_search('社群熱推', $headings, true));

        $firstMenuItem = $xpath->query('//*[@id="product_menu"]/a')->item(0);
        $this->assertSame('#social', $firstMenuItem->getAttribute('href'));
        $this->assertSame('社群熱推', trim($firstMenuItem->textContent));
    }

    public function test_platform_embed_is_built_from_the_url_only(): void
    {
        $this->seedPost('Meta', [
            'canonical_url' => 'https://www.threads.com/@meta/post/DDzbnVKx57R',
            'text' => '只在卡片上出現的內文',
        ]);

        $xpath = $this->xpath($this->productPage());
        $template = $xpath->query('//template[@data-embed="0"]')->item(0);
        $embed = $xpath->query('.//blockquote[contains(@class, "text-post-media")]', $template)->item(0);
        $fallback = $xpath->query('.//*[contains(@class, "uq-social-fallback")]', $template)->item(0);

        $this->assertSame('https://www.threads.com/t/DDzbnVKx57R', $embed->getAttribute('data-text-post-permalink'));
        $this->assertStringNotContainsString('只在卡片上出現的內文', $embed->textContent);
        // 平台嵌入被擋時才顯示的備用卡片，用的是自己存的內容
        $this->assertStringContainsString('只在卡片上出現的內文', $fallback->textContent);
    }

    public function test_post_that_cannot_be_embedded_opens_straight_to_the_fallback_card(): void
    {
        $this->seedPost('Meta (@meta)', [
            'canonical_url' => 'https://www.threads.com/@meta/post/DDzbnVKx57R',
            'text' => '無法嵌入的貼文',
            'embeddable' => false,
        ]);
        $this->seedPost('Meta (@meta)', ['canonical_url' => 'https://www.threads.com/@meta/post/C1', 'text' => '可以嵌入的貼文']);

        $xpath = $this->xpath($this->productPage());
        $embedOf = fn (string $text) => $xpath->query('//template[@data-embed][contains(., "'.$text.'")]')->item(0);
        $embedError = $embedOf('無法嵌入的貼文');
        $allowed = $embedOf('可以嵌入的貼文');
        $fallback = fn ($template) => $xpath->query('.//*[contains(@class, "uq-social-fallback")]', $template)->item(0);

        // 平台的嵌入只會顯示「無法顯示」，不載入，直接給自己存的內容
        $this->assertStringContainsString('無法嵌入的貼文', $fallback($embedError)->textContent);
        $this->assertSame(0, $xpath->query('.//*[contains(@class, "text-post-media")]', $embedError)->length);
        $this->assertFalse($fallback($embedError)->hasAttribute('hidden'));

        $this->assertStringContainsString('可以嵌入的貼文', $fallback($allowed)->textContent);
        $this->assertSame(1, $xpath->query('.//*[contains(@class, "text-post-media")]', $allowed)->length);
        $this->assertTrue($fallback($allowed)->hasAttribute('hidden'));
    }

    public function test_fallback_card_drops_its_image_when_it_fails_to_load(): void
    {
        $this->seedPost('Meta (@meta)', ['cover_url' => 'https://scontent.cdninstagram.com/p.jpg?oe=6AD5FF99', 'text' => '圖片以外的內文']);

        $xpath = $this->xpath($this->productPage());
        $fallback = $xpath->query('//template[@data-embed="0"]//*[contains(@class, "uq-social-fallback")]')->item(0);

        // 預覽圖抓不到時把圖片那一格整個拿掉、只留文字，不顯示破圖
        $image = $xpath->query('.//*[contains(@class, "image")]/img', $fallback)->item(0);
        $this->assertSame('this.parentNode.remove()', $image->getAttribute('onerror'));
        $this->assertSame('圖片以外的內文', trim($xpath->query('.//*[contains(@class, "description")]', $fallback)->item(0)->textContent));
    }

    public function test_facebook_embed_uses_the_official_script_so_its_height_follows_the_post(): void
    {
        $this->seedPost('Facebook', ['platform' => SocialPlatform::Facebook, 'kind' => SocialPostKind::Post, 'canonical_url' => 'https://www.facebook.com/20531316728/posts/10154009990506729']);
        $this->seedPost('Facebook', ['platform' => SocialPlatform::Facebook, 'kind' => SocialPostKind::Video, 'canonical_url' => 'https://www.facebook.com/reel/1']);

        $html = $this->productPage();
        $xpath = $this->xpath($html);
        $post = $xpath->query('//template//*[contains(concat(" ", normalize-space(@class), " "), " fb-post ")]')->item(0);
        $video = $xpath->query('//template//*[contains(concat(" ", normalize-space(@class), " "), " fb-video ")]')->item(0);

        $this->assertSame('https://www.facebook.com/20531316728/posts/10154009990506729', $post->getAttribute('data-href'));
        $this->assertSame('https://www.facebook.com/reel/1', $video->getAttribute('data-href'));
        $this->assertStringNotContainsString('facebook.com/plugins/', $html);
        // 影片才開全螢幕，官方設定預設是關的
        $this->assertSame('true', $video->getAttribute('data-allowfullscreen'));
        $this->assertFalse($post->hasAttribute('data-allowfullscreen'));
    }

    /**
     * @return array<int, \DOMElement>
     */
    private function cards(DOMXPath $xpath): array
    {
        return iterator_to_array($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " uq-social-card ")]'));
    }

    /** 卡片上顯示的帳號是這個的那一張 */
    private function cardBy(DOMXPath $xpath, string $author): \DOMElement
    {
        $cards = array_values(array_filter(
            $this->cards($xpath),
            fn ($card) => trim($xpath->query('.//*[contains(@class, "uq-social-author")]', $card)->item(0)->textContent) === $author
        ));
        $this->assertCount(1, $cards, "找不到 {$author} 的卡片");

        return $cards[0];
    }

    /**
     * @return array<int, string>
     */
    private function authors(DOMXPath $xpath): array
    {
        return array_map(
            fn ($card) => trim($xpath->query('.//*[contains(@class, "uq-social-author")]', $card)->item(0)->textContent),
            $this->cards($xpath)
        );
    }

    /** 沒有預覽圖的卡片直接顯示文字，不是藏起來等圖片失敗才出現 */
    private function assertVisibleQuote(DOMXPath $xpath, \DOMElement $card, string $text): void
    {
        $quote = $xpath->query('.//*[contains(@class, "uq-social-quote")]', $card)->item(0);

        $this->assertFalse($quote->hasAttribute('hidden'));
        $this->assertStringContainsString($text, $quote->textContent);
    }
}
