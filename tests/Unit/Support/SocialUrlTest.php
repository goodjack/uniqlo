<?php

namespace Tests\Unit\Support;

use App\Support\SocialUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SocialUrlTest extends TestCase
{
    public static function canonicalUrls(): array
    {
        return [
            'YouTube 分享帶 si' => ['https://youtube.com/watch?v=M7lc1UVf-VE&si=abc', 'https://www.youtube.com/watch?v=M7lc1UVf-VE'],
            'YouTube 短網址' => ['https://youtu.be/M7lc1UVf-VE?t=3', 'https://www.youtube.com/watch?v=M7lc1UVf-VE'],
            'Shorts 保留 Shorts' => ['https://youtube.com/shorts/7lCDEYXw3mM?si=abc', 'https://www.youtube.com/shorts/7lCDEYXw3mM'],
            'IG 追蹤參數' => ['https://www.instagram.com/p/fA9uwTtkSN/?igsh=abc', 'https://www.instagram.com/p/fA9uwTtkSN/'],
            'IG Reels' => ['https://www.instagram.com/reel/fA9uwTtkSN/?igsh=abc', 'https://www.instagram.com/reel/fA9uwTtkSN/'],
            'Threads 語言參數與舊網域' => ['https://www.threads.net/@meta/post/DDzbnVKx57R?hl=zh-tw', 'https://www.threads.com/@meta/post/DDzbnVKx57R'],
            'X 舊網域' => ['https://twitter.com/Interior/status/507185938620219395?s=20', 'https://x.com/Interior/status/507185938620219395'],
            'FB 轉址後的追蹤參數' => ['https://www.facebook.com/20531316728/posts/10154009990506729?rdid=abc&share_url=x', 'https://www.facebook.com/20531316728/posts/10154009990506729'],
            'FB 手機網域' => ['https://m.facebook.com/reel/2/?locale=zh_TW', 'https://www.facebook.com/reel/2'],
            'FB 影片頁留 v' => ['https://www.facebook.com/watch/?v=1&ref=sharing', 'https://www.facebook.com/watch/?v=1'],
            'FB 粉專影片去掉標題段' => ['https://www.facebook.com/facebook/videos/title/10153231379946729/', 'https://www.facebook.com/facebook/videos/10153231379946729'],
            'FB 粉專影片' => ['https://www.facebook.com/facebook/videos/10153231379946729', 'https://www.facebook.com/facebook/videos/10153231379946729'],
            'YouTube 直播' => ['https://www.youtube.com/live/M7lc1UVf-VE?si=abc', 'https://www.youtube.com/watch?v=M7lc1UVf-VE'],
            'YouTube 嵌入網址' => ['https://www.youtube.com/embed/M7lc1UVf-VE', 'https://www.youtube.com/watch?v=M7lc1UVf-VE'],
            'YouTube 手機網域與播放清單' => ['https://m.youtube.com/watch?v=M7lc1UVf-VE&list=PLabc&index=2', 'https://www.youtube.com/watch?v=M7lc1UVf-VE'],
            'IG Reels 複數' => ['https://www.instagram.com/reels/fA9uwTtkSN/', 'https://www.instagram.com/reel/fA9uwTtkSN/'],
            'IG 舊版影片' => ['https://www.instagram.com/tv/fA9uwTtkSN/', 'https://www.instagram.com/reel/fA9uwTtkSN/'],
            'IG 帳號在前' => ['https://www.instagram.com/instagram/p/fA9uwTtkSN/', 'https://www.instagram.com/p/fA9uwTtkSN/'],
            'Threads 尾巴多了 media' => ['https://www.threads.com/@meta/post/DDzbnVKx57R/media', 'https://www.threads.com/@meta/post/DDzbnVKx57R'],
            'X 照片尾巴' => ['https://x.com/Interior/status/463440424141459456/photo/1', 'https://x.com/Interior/status/463440424141459456'],
            'X 影片尾巴' => ['https://x.com/Interior/status/463440424141459456/video/1', 'https://x.com/Interior/status/463440424141459456'],
            'X 帳號有底線' => ['https://x.com/UNIQLO_JP/status/1?s=20', 'https://x.com/UNIQLO_JP/status/1'],
            'Threads 帳號有底線' => ['https://www.threads.com/@gu_taiwan/post/DDzbnVKx57R', 'https://www.threads.com/@gu_taiwan/post/DDzbnVKx57R'],
            'X 手機網域' => ['https://mobile.twitter.com/Interior/status/463440424141459456', 'https://x.com/Interior/status/463440424141459456'],
            'X 沒有帳號' => ['https://x.com/i/web/status/463440424141459456', 'https://x.com/i/status/463440424141459456'],
            'X 沒有帳號的短版' => ['https://twitter.com/i/status/463440424141459456', 'https://x.com/i/status/463440424141459456'],
            'FB web 網域' => ['https://web.facebook.com/20531316728/posts/10154009990506729', 'https://www.facebook.com/20531316728/posts/10154009990506729'],
        ];
    }

    #[DataProvider('canonicalUrls')]
    public function test_canonical_drops_tracking_and_unifies_domains(string $url, string $expected): void
    {
        $this->assertSame($expected, SocialUrl::canonical($url));
    }

    public static function shareLinks(): array
    {
        return [
            'Threads' => ['https://www.threads.com/share/AbCdEf1234/'],
            'FB 貼文' => ['https://www.facebook.com/share/p/AbCdEf5678/'],
            'FB Reels' => ['https://www.facebook.com/share/r/AbCdEf9012/'],
            'fb.watch' => ['https://fb.watch/abcDEF123/'],
            'Threads 短網址' => ['https://www.threads.com/t/DDzbnVKx57R'],
            'Threads 舊網域短網址' => ['https://www.threads.net/t/DDzbnVKx57R/'],
            'FB 影片' => ['https://www.facebook.com/share/v/AbCdEf3456/'],
        ];
    }

    #[DataProvider('shareLinks')]
    public function test_share_links_need_redirect_before_they_have_a_canonical_url(string $url): void
    {
        $this->assertTrue(SocialUrl::isShareLink($url));
        $this->assertNull(SocialUrl::canonical($url));
    }

    public static function unsupportedFacebookUrls(): array
    {
        return [
            '相片頁要登入' => ['https://www.facebook.com/photo/?fbid=3&set=a.4'],
            '登入頁' => ['https://www.facebook.com/login/?next=x'],
            '舊登入頁' => ['https://www.facebook.com/login.php?next=x'],
            '首頁' => ['https://www.facebook.com/'],
            '粉專首頁' => ['https://www.facebook.com/facebook/'],
            '編號在參數裡的舊網址' => ['https://www.facebook.com/permalink.php?story_fbid=1&id=2'],
            '影片頁沒有 v' => ['https://www.facebook.com/watch/'],
            '社團貼文要登入' => ['https://www.facebook.com/groups/abc/posts/123/'],
        ];
    }

    #[DataProvider('unsupportedFacebookUrls')]
    public function test_facebook_urls_that_do_not_point_to_a_post_are_not_supported(string $url): void
    {
        $this->assertNull(SocialUrl::canonical($url));
    }

    public function test_ids_must_end_where_the_path_segment_ends(): void
    {
        $this->assertNull(SocialUrl::canonical('https://x.com/i/status/123abc'));
        $this->assertNull(SocialUrl::canonical('https://x.com/Interior/status/123abc'));
        $this->assertFalse(SocialUrl::isShareLink('https://www.threads.com/t/'));
    }

    public function test_profile_and_story_pages_are_not_posts(): void
    {
        $this->assertNull(SocialUrl::canonical('https://www.instagram.com/stories/instagram/1/'));
        $this->assertNull(SocialUrl::canonical('https://www.instagram.com/instagram/'));
        $this->assertNull(SocialUrl::canonical('https://www.threads.com/@meta'));
        $this->assertNull(SocialUrl::canonical('https://x.com/Interior'));
        $this->assertNull(SocialUrl::canonical('https://www.youtube.com/@YouTube'));
    }

    public function test_platform_is_decided_by_host_not_by_substring(): void
    {
        $this->assertSame('instagram', SocialUrl::platform('https://www.instagram.com/p/abc/'));
        $this->assertNull(SocialUrl::platform('https://192.168.1.1/?instagram.com'));
        $this->assertNull(SocialUrl::platform('https://evilinstagram.com/p/abc/'));
        $this->assertNull(SocialUrl::platform('http://www.instagram.com/p/abc/'));
    }

    public function test_fetch_hosts_are_exactly_the_platform_hosts(): void
    {
        $this->assertSame([
            'youtube.com', 'youtu.be',
            'instagram.com',
            'threads.com', 'threads.net',
            'x.com', 'twitter.com',
            'facebook.com', 'fb.watch',
        ], SocialUrl::fetchHosts());
    }

    public function test_kind_follows_the_url_shape(): void
    {
        $this->assertSame('Shorts', SocialUrl::kind('https://www.youtube.com/shorts/7lCDEYXw3mM'));
        $this->assertSame('影片', SocialUrl::kind('https://www.youtube.com/watch?v=M7lc1UVf-VE'));
        $this->assertSame('Reels', SocialUrl::kind('https://www.instagram.com/reel/fA9uwTtkSN/'));
        $this->assertSame('貼文', SocialUrl::kind('https://www.threads.com/@meta/post/DDzbnVKx57R'));
        $this->assertSame('影片', SocialUrl::kind('https://www.facebook.com/reel/2'));
        $this->assertSame('影片', SocialUrl::kind('https://www.facebook.com/facebook/videos/10153231379946729'));
        $this->assertSame('影片', SocialUrl::kind('https://www.facebook.com/watch/?v=1'));
        // 粉專名稱剛好以 watch、reel 開頭的貼文不算影片
        $this->assertSame('貼文', SocialUrl::kind('https://www.facebook.com/watchdog.tw/posts/pfbid0'));
        $this->assertSame('貼文', SocialUrl::kind('https://www.facebook.com/reelshop/posts/pfbid0'));
    }
}
