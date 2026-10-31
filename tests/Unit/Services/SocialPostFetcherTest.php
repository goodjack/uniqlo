<?php

namespace Tests\Unit\Services;

use App\Services\SocialPostFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialPostFetcherTest extends TestCase
{
    private const OEMBED_HTML = '<blockquote class="text-post-media">貼文</blockquote><script async src="https://www.threads.com/embed.js"></script>';

    private function fetch(string $url): array
    {
        return app(SocialPostFetcher::class)->fetch($url);
    }

    public function test_threads_card_comes_from_the_page_preview_tags(): void
    {
        Http::fake([
            'graph.threads.net/*' => Http::response(['html' => self::OEMBED_HTML]),
            'www.threads.com/*' => Http::response(
                '<meta property="og:title" content="Tony Wang (&#064;haku_ou1015) on Threads" />'
                .'<meta property="og:description" content="更：把鞋子內搭換過了" />'
                .'<meta property="og:image" content="https://scontent.cdninstagram.com/p.jpg?a=1&amp;oe=6AD5FF99" />'
            ),
        ]);

        $post = $this->fetch('https://www.threads.com/@haku_ou1015/post/DIqyoWbyeky?hl=zh-tw');

        $this->assertSame('https://www.threads.com/@haku_ou1015/post/DIqyoWbyeky', $post['canonical_url']);
        $this->assertSame('Tony Wang (@haku_ou1015)', $post['author']);
        $this->assertSame('更：把鞋子內搭換過了', $post['text']);
        $this->assertSame('https://scontent.cdninstagram.com/p.jpg?a=1&oe=6AD5FF99', $post['cover_url']);
        // 失效時間取自網址的 oe 參數（16 進位 Unix 時間）
        $this->assertSame(hexdec('6AD5FF99'), $post['cover_expires_at']->getTimestamp());
        $this->assertArrayNotHasKey('embed_html', $post);
    }

    public function test_requests_say_who_we_are_and_never_touch_the_embed_pages(): void
    {
        Http::fake([
            'graph.threads.net/*' => Http::response(['html' => self::OEMBED_HTML]),
            'www.threads.com/*' => Http::response(''),
        ]);

        $this->fetch('https://www.threads.com/@uniqlo/post/DGxKQj5hK21');

        Http::assertSent(fn (Request $request) => $request->header('User-Agent') === [SocialPostFetcher::USER_AGENT]);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/embed'));
    }

    public function test_preview_image_from_unknown_hosts_is_dropped(): void
    {
        Http::fake([
            'graph.threads.net/*' => Http::response(['html' => self::OEMBED_HTML]),
            'www.threads.com/*' => Http::response('<meta property="og:image" content="https://evil.example.com/p.jpg?oe=6AD5FF99" />'),
        ]);

        $post = $this->fetch('https://www.threads.com/@uniqlo/post/DGxKQj5hK21');

        $this->assertNull($post['cover_url']);
        $this->assertNull($post['cover_expires_at']);
    }

    public function test_author_is_cut_to_fit_when_the_title_is_not_in_the_usual_format(): void
    {
        Http::fake([
            'graph.threads.net/*' => Http::response(['html' => self::OEMBED_HTML]),
            'www.threads.com/*' => Http::response('<meta property="og:title" content="'.str_repeat('長', 300).'" />'),
        ]);

        $post = $this->fetch('https://www.threads.com/@uniqlo/post/DGxKQj5hK21');

        $this->assertLessThanOrEqual(255, mb_strlen($post['author']));
    }

    public function test_threads_share_link_is_resolved_to_the_post_url(): void
    {
        Http::fake([
            'www.threads.com/share/*' => Http::response('<meta property="og:url" content="https://www.threads.com/&#064;geelou0406/post/DWMAZ2UgRQi" />'),
            'graph.threads.net/*' => Http::response(['html' => self::OEMBED_HTML]),
            'www.threads.com/*' => Http::response(''),
        ]);

        $post = $this->fetch('https://www.threads.com/share/BAXKotUx6Y/');

        $this->assertSame('https://www.threads.com/@geelou0406/post/DWMAZ2UgRQi', $post['canonical_url']);
    }

    public function test_facebook_share_link_is_resolved_to_the_post_it_redirects_to(): void
    {
        Http::fake([
            'www.facebook.com/share/*' => Http::response('', 302, ['Location' => 'https://www.facebook.com/gu.taiwan/posts/pfbid0bxFq?rdid=PTo9&share_url=x']),
            'www.facebook.com/*' => Http::response('<meta property="og:title" content="GU Taiwan" /><meta property="og:description" content="店舖營業時間調整公告" />'),
        ]);

        $post = $this->fetch('https://www.facebook.com/share/p/19ztyAj4YK/');

        $this->assertSame('https://www.facebook.com/gu.taiwan/posts/pfbid0bxFq', $post['canonical_url']);
        $this->assertSame('GU Taiwan', $post['author']);
        $this->assertSame('貼文', $post['kind']);
    }

    public static function reelTitles(): array
    {
        return [
            '觀看數開頭' => ['69K views &#xb7; 165 reactions | GU好搭針織！ | 下一行 | GU Taiwan'],
            '單數觀看' => ['1 view &#xb7; 2 reactions | 內文 | GU Taiwan'],
            '只有心情數' => ['165 reactions &#xb7; 3 comments | 內文 | GU Taiwan'],
        ];
    }

    #[DataProvider('reelTitles')]
    public function test_facebook_reel_author_is_the_page_at_the_end_of_the_title(string $title): void
    {
        Http::fake([
            'www.facebook.com/*' => Http::response('<meta property="og:title" content="'.$title.'" />'),
        ]);

        $post = $this->fetch('https://www.facebook.com/reel/1090493390461918/');

        $this->assertSame('GU Taiwan', $post['author']);
        $this->assertSame('影片', $post['kind']);
    }

    public function test_facebook_post_title_with_a_bar_is_kept_whole(): void
    {
        Http::fake(['www.facebook.com/*' => Http::response('<meta property="og:title" content="GU | 台灣" />')]);

        $post = $this->fetch('https://www.facebook.com/gu.taiwan/posts/pfbid0bxFq');

        $this->assertSame('GU | 台灣', $post['author']);
    }

    public function test_facebook_share_link_that_lands_on_the_login_page_is_rejected(): void
    {
        Http::fake([
            'www.facebook.com/share/*' => Http::response('', 302, ['Location' => 'https://www.facebook.com/login/?next=x']),
            'www.facebook.com/login*' => Http::response('<meta property="og:url" content="https://www.facebook.com/" />'),
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->fetch('https://www.facebook.com/share/p/19ztyAj4YK/');
    }

    public function test_instagram_detects_reels_from_the_page_url(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['html' => '<blockquote class="instagram-media"></blockquote>']),
            'www.instagram.com/*' => Http::response(
                '<meta property="og:title" content="86 on Instagram: &quot;UNIQLO 推薦&quot;" />'
                .'<meta property="og:description" content="6,226 likes, 118 comments - lin_86__ on September 2, 2026: &quot;UNIQLO 推薦&quot;" />'
                .'<meta property="og:url" content="https://www.instagram.com/lin_86__/reel/Dcx6CJmzjTX/" />'
                .'<meta property="og:image" content="https://scontent.cdninstagram.com/square.jpg?stp=c288.0.864.864a" />'
            ),
        ]);

        $post = $this->fetch('https://www.instagram.com/p/Dcx6CJmzjTX/?exln=abc');

        $this->assertSame('Reels', $post['kind']);
        $this->assertSame('86 (@lin_86__)', $post['author']);
        $this->assertSame('UNIQLO 推薦', $post['text']);
        $this->assertSame('https://scontent.cdninstagram.com/square.jpg?stp=c288.0.864.864a', $post['cover_url']);
    }

    public static function instagramDescriptions(): array
    {
        return [
            '有讚數' => ['2,082 likes, 9 comments - uniqlo on October 16, 2026: &quot;Fleece is an essential.&quot;. ', 'UNIQLO Global (@uniqlo)', 'Fleece is an essential.'],
            '沒有讚數、被截斷' => ['uniqlo on September 30, 2026: &quot;ジャケットの下に', 'UNIQLO Global (@uniqlo)', 'ジャケットの下に'],
        ];
    }

    #[DataProvider('instagramDescriptions')]
    public function test_instagram_caption_and_handle_come_from_the_description(string $description, string $author, string $text): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['html' => '<blockquote class="instagram-media"></blockquote>']),
            'www.instagram.com/*' => Http::response(
                '<meta property="og:title" content="UNIQLO Global on Instagram: &quot;Fleece&quot;" />'
                .'<meta property="og:description" content="'.$description.'" />'
            ),
        ]);

        $post = $this->fetch('https://www.instagram.com/p/DejKT8aH03w/');

        $this->assertSame($author, $post['author']);
        $this->assertSame($text, $post['text']);
    }

    private const X_OEMBED = [
        'author_name' => '美国之音中文网',
        'html' => '<blockquote class="twitter-tweet"><p lang="zh">優衣庫&amp;東京</p>&mdash; VOA</blockquote><script src="https://platform.twitter.com/widgets.js"></script>',
    ];

    public static function xPreviewImages(): array
    {
        return [
            '影片' => [
                'https://pbs.twimg.com/amplify_video_thumb/1737165473676931072/img/7OGLjITSntk646ds?format=webp&amp;name=large',
                'https://pbs.twimg.com/amplify_video_thumb/1737165473676931072/img/7OGLjITSntk646ds?format=webp&name=large',
                '影片',
            ],
            'GIF' => ['https://pbs.twimg.com/tweet_video_thumb/GabcDEF.jpg', 'https://pbs.twimg.com/tweet_video_thumb/GabcDEF.jpg', '影片'],
            '照片' => ['https://pbs.twimg.com/media/GabcDEF?format=jpg&amp;name=large', 'https://pbs.twimg.com/media/GabcDEF?format=jpg&name=large', '貼文'],
            // 純文字貼文給的是 X 的標誌圖，檔名的 hash 會變
            '純文字' => ['https://abs.twimg.com/x-web/assets/og/default-og.7d3a1c2b.jpg', null, '貼文'],
            '標誌圖換了主機' => ['https://pbs.twimg.com/x-web/assets/og/default-og.9e8f.jpg', null, '貼文'],
        ];
    }

    #[DataProvider('xPreviewImages')]
    public function test_x_cover_comes_from_the_page_preview_image(string $ogImage, ?string $cover, string $kind): void
    {
        Http::fake([
            'publish.twitter.com/*' => Http::response(self::X_OEMBED),
            'x.com/*' => Http::response('<meta property="og:type" content="article"/><meta property="og:image" content="'.$ogImage.'"/>'),
        ]);

        $post = $this->fetch('https://twitter.com/VOAChinese/status/1737165626911662317?s=20');

        $this->assertSame('https://x.com/VOAChinese/status/1737165626911662317', $post['canonical_url']);
        $this->assertSame('美国之音中文网', $post['author']);
        $this->assertSame('優衣庫&東京', $post['text']);
        $this->assertSame($cover, $post['cover_url']);
        $this->assertSame($kind, $post['kind']);
        // X 的圖網址沒有 oe 參數，不設失效時間，跟著每天整批重抓更新
        $this->assertNull($post['cover_expires_at']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://x.com/VOAChinese/status/1737165626911662317'
            && $request->header('User-Agent') === [SocialPostFetcher::USER_AGENT]);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'syndication'));
    }

    public function test_x_page_failure_falls_back_to_a_text_card(): void
    {
        Http::fake([
            'publish.twitter.com/*' => Http::response(self::X_OEMBED),
            'x.com/*' => Http::response('', 403),
        ]);

        $post = $this->fetch('https://x.com/VOAChinese/status/1737165626911662317');

        $this->assertSame('優衣庫&東京', $post['text']);
        $this->assertNull($post['cover_url']);
        $this->assertSame('貼文', $post['kind']);
    }

    public function test_x_page_rate_limit_is_passed_on_instead_of_dropping_the_preview(): void
    {
        Http::fake([
            'publish.twitter.com/*' => Http::response(self::X_OEMBED),
            'x.com/*' => Http::response('', 429),
        ]);

        $this->expectException(RequestException::class);

        $this->fetch('https://x.com/VOAChinese/status/1737165626911662317');
    }

    public function test_youtube_keeps_the_video_id_and_title(): void
    {
        Http::fake(['www.youtube.com/oembed*' => Http::response(['author_name' => '質男日著 WearLife', 'title' => 'UNIQLO C系列好物必買！'])]);

        $post = $this->fetch('https://youtube.com/shorts/T4Q6DY8xJgQ?si=abc');

        $this->assertSame('Shorts', $post['kind']);
        $this->assertSame('T4Q6DY8xJgQ', $post['youtube_id']);
        $this->assertSame('UNIQLO C系列好物必買！', $post['text']);
        $this->assertNull($post['cover_url']);
    }

    public function test_unknown_hosts_are_rejected_before_any_request(): void
    {
        Http::fake();

        try {
            $this->fetch('https://192.168.1.1/?instagram.com');
            $this->fail('應該要擋下');
        } catch (InvalidArgumentException) {
            Http::assertNothingSent();
        }
    }
}
