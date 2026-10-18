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
            'YouTube 分享帶 si' => ['https://youtube.com/watch?v=VJyhv7PvLdU&si=Woxig', 'https://www.youtube.com/watch?v=VJyhv7PvLdU'],
            'YouTube 短網址' => ['https://youtu.be/VJyhv7PvLdU?t=3', 'https://www.youtube.com/watch?v=VJyhv7PvLdU'],
            'Shorts 保留 Shorts' => ['https://youtube.com/shorts/f0VtQlsYggQ?si=Ydry', 'https://www.youtube.com/shorts/f0VtQlsYggQ'],
            'IG 追蹤參數' => ['https://www.instagram.com/p/DejKT8aH03w/?obrf=MWN2', 'https://www.instagram.com/p/DejKT8aH03w/'],
            'IG Reels' => ['https://www.instagram.com/reel/Dcx6CJmzjTX/?exln=MWdh', 'https://www.instagram.com/reel/Dcx6CJmzjTX/'],
            'Threads 語言參數與舊網域' => ['https://www.threads.net/@uniqlo/post/DGxKQj5hK21?hl=zh-tw', 'https://www.threads.com/@uniqlo/post/DGxKQj5hK21'],
            'X 舊網域' => ['https://twitter.com/ea_eran/status/1299301346047262722?s=20', 'https://x.com/ea_eran/status/1299301346047262722'],
        ];
    }

    #[DataProvider('canonicalUrls')]
    public function test_canonical_drops_tracking_and_unifies_domains(string $url, string $expected): void
    {
        $this->assertSame($expected, SocialUrl::canonical($url));
    }

    public function test_threads_share_link_needs_redirect_before_it_has_a_canonical_url(): void
    {
        $this->assertTrue(SocialUrl::isThreadsShareLink('https://www.threads.com/share/BAXKotUx6Y/'));
        $this->assertNull(SocialUrl::canonical('https://www.threads.com/share/BAXKotUx6Y/'));
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
        $this->assertSame('Shorts', SocialUrl::kind('https://www.youtube.com/shorts/f0VtQlsYggQ'));
        $this->assertSame('影片', SocialUrl::kind('https://www.youtube.com/watch?v=VJyhv7PvLdU'));
        $this->assertSame('Reels', SocialUrl::kind('https://www.instagram.com/reel/Dcx6CJmzjTX/'));
        $this->assertSame('貼文', SocialUrl::kind('https://www.threads.com/@uniqlo/post/DGxKQj5hK21'));
    }
}
