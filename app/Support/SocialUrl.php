<?php

namespace App\Support;

class SocialUrl
{
    public const PLATFORM_HOSTS = [
        'youtube' => ['youtube.com', 'youtu.be'],
        'instagram' => ['instagram.com'],
        'threads' => ['threads.com', 'threads.net'],
        'x' => ['x.com', 'twitter.com'],
        'facebook' => ['facebook.com', 'fb.watch'],
    ];

    /** 預覽圖只收 Meta 與 X 的圖片 CDN；轉送圖片時也只連這些主機 */
    public const COVER_HOSTS = ['cdninstagram.com', 'fbcdn.net', 'pbs.twimg.com'];

    /**
     * oEmbed 端點與轉址目前都落在平台本身的網域底下；平台以外的主機要放行時另外列，不加進平台清單。
     *
     * @return list<string>
     */
    public static function fetchHosts(): array
    {
        return array_merge(...array_values(self::PLATFORM_HOSTS));
    }

    public static function hostMatches(string $url, array $suffixes): bool
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        foreach ($suffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    /** 純文字貼文的 og:image 是 X 的標誌圖（abs.twimg.com/.../default-og.<hash>.jpg），檔名的 hash 會變 */
    public static function isXDefaultImage(string $url): bool
    {
        return self::hostMatches($url, ['abs.twimg.com']) || str_contains((string) parse_url($url, PHP_URL_PATH), 'default-og');
    }

    public static function platform(string $url): ?string
    {
        foreach (self::PLATFORM_HOSTS as $platform => $hosts) {
            if (self::hostMatches($url, $hosts)) {
                return $platform;
            }
        }

        return null;
    }

    public static function youtubeId(string $url): ?string
    {
        return preg_match('~(?:[?&]v=|/shorts/|youtu\.be/|/embed/|/live/)([A-Za-z0-9_-]{11})~', $url, $m) ? $m[1] : null;
    }

    /** 分享短網址看不出是哪則，要先追轉址：Threads 與 Facebook 的 /share/，以及 fb.watch */
    public static function isShareLink(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return match (self::platform($url)) {
            'threads' => str_contains($path, '/share/'),
            'facebook' => str_starts_with($path, '/share/') || self::hostMatches($url, ['fb.watch']),
            default => false,
        };
    }

    /**
     * 去掉追蹤參數、統一網域，同一則貼文不管從哪裡分享都得到同一個網址。
     * 分享短網址要先追轉址才知道是哪則，這裡不處理。
     */
    public static function canonical(string $url): ?string
    {
        $platform = self::platform($url);
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return match ($platform) {
            'youtube' => ($id = self::youtubeId($url))
                ? (str_contains($path, '/shorts/') ? "https://www.youtube.com/shorts/{$id}" : "https://www.youtube.com/watch?v={$id}")
                : null,
            'instagram' => preg_match('~/(p|reel|reels|tv)/([A-Za-z0-9_-]+)~', $path, $m)
                ? 'https://www.instagram.com/'.($m[1] === 'p' ? 'p' : 'reel')."/{$m[2]}/"
                : null,
            'threads' => preg_match('~/(@[A-Za-z0-9_.]+)/post/([A-Za-z0-9_-]+)~', $path, $m)
                ? "https://www.threads.com/{$m[1]}/post/{$m[2]}"
                : null,
            'x' => preg_match('~/([A-Za-z0-9_]+)/status/(\d+)~', $path, $m)
                ? "https://x.com/{$m[1]}/status/{$m[2]}"
                : null,
            'facebook' => self::facebookCanonical($url, $path),
            default => null,
        };
    }

    /**
     * 只認得出是哪一則的網址：粉專貼文、粉專影片、Reels，以及編號在 v 參數裡的影片頁。
     * 相片頁、首頁、登入頁這些沒登入讀不到或不是貼文的，當作不支援，免得把登入頁存成貼文。
     */
    private static function facebookCanonical(string $url, string $path): ?string
    {
        if ($path === '/watch') {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return isset($query['v']) && ctype_digit($query['v']) ? "https://www.facebook.com/watch/?v={$query['v']}" : null;
        }

        if (self::isShareLink($url)) {
            return null;
        }

        // 粉專影片網址中間可能夾著標題，只留編號，同一支影片才會得到同一個網址
        if (preg_match('~^/([^/]+)/videos/(?:[^/]+/)?(\d+)$~', $path, $m)) {
            return "https://www.facebook.com/{$m[1]}/videos/{$m[2]}";
        }

        return preg_match('~^/(reel/\d+|[^/]+/posts/[^/]+)$~', $path) ? 'https://www.facebook.com'.$path : null;
    }

    public static function kind(string $canonicalUrl): string
    {
        return match (self::platform($canonicalUrl)) {
            'youtube' => str_contains($canonicalUrl, '/shorts/') ? 'Shorts' : '影片',
            'instagram' => str_contains($canonicalUrl, '/reel/') ? 'Reels' : '貼文',
            'facebook' => preg_match('~^https://www\.facebook\.com/(reel/|watch/|[^/]+/videos/)~', $canonicalUrl) ? '影片' : '貼文',
            default => '貼文',
        };
    }
}
