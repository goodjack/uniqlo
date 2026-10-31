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

    public static function isThreadsShareLink(string $url): bool
    {
        return self::platform($url) === 'threads' && str_contains((string) parse_url($url, PHP_URL_PATH), '/share/');
    }

    /**
     * 去掉追蹤參數、統一網域，同一則貼文不管從哪裡分享都得到同一個網址。
     * Threads 分享短網址要先追轉址才知道是哪則，這裡不處理。
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
            'facebook' => 'https://'.parse_url($url, PHP_URL_HOST).$path,
            default => null,
        };
    }

    public static function kind(string $canonicalUrl): string
    {
        return match (self::platform($canonicalUrl)) {
            'youtube' => str_contains($canonicalUrl, '/shorts/') ? 'Shorts' : '影片',
            'instagram' => str_contains($canonicalUrl, '/reel/') ? 'Reels' : '貼文',
            'facebook' => preg_match('~/(videos|reel|watch)~', $canonicalUrl) ? '影片' : '貼文',
            default => '貼文',
        };
    }
}
