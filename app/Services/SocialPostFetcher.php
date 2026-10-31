<?php

namespace App\Services;

use App\Support\SocialUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * 從貼文網址抓出卡片要用的資料。
 *
 * 官方 oEmbed（免金鑰）用來確認貼文還在、可以嵌入，回傳的 HTML 不存也不輸出；彈出視窗的嵌入標記由頁面用網址自己組。
 * IG、Threads 的預覽圖與作者、內文，以及 X 的預覽圖，取自頁面給連結預覽用的 og 標籤，只在新加或快過期時讀一次；
 * 讀不到就退回文字卡。
 */
class SocialPostFetcher
{
    // 誠實標明身分，不假冒平台自己的爬蟲
    public const USER_AGENT = 'Mozilla/5.0 (compatible; UQSearchPreview/1.0; +https://uq.goodjack.tw)';

    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @return array{platform: string, kind: string, canonical_url: string, author: ?string, text: ?string,
     *     youtube_id: ?string, cover_url: ?string, cover_expires_at: ?Carbon}
     */
    public function fetch(string $url): array
    {
        if (SocialUrl::isShareLink($url)) {
            $url = $this->resolveRedirect($url);
        }

        $platform = SocialUrl::platform($url);
        $canonical = SocialUrl::canonical($url);

        if ($platform === null || $canonical === null) {
            throw new InvalidArgumentException("不支援的網址：{$url}");
        }

        $post = [
            'platform' => $platform,
            'kind' => SocialUrl::kind($canonical),
            'canonical_url' => $canonical,
            'author' => null,
            'text' => null,
            'youtube_id' => null,
            'cover_url' => null,
        ];

        $post = match ($platform) {
            'youtube' => $this->youtube($post),
            'instagram' => $this->instagram($post),
            'threads' => $this->threads($post),
            'x' => $this->x($post),
            'facebook' => $this->facebook($post),
        };

        $post['text'] = $post['text'] === null ? null : Str::limit(trim($post['text']), 500);
        // 標題沒對到格式時整串會變成作者，截短才存得進 author 欄
        $post['author'] = $post['author'] === null ? null : Str::limit($post['author'], 250);
        if ($post['cover_url'] !== null && ! SocialUrl::hostMatches($post['cover_url'], SocialUrl::COVER_HOSTS)) {
            $post['cover_url'] = null;
        }
        $post['cover_expires_at'] = $this->expiresAt($post['cover_url']);

        return $post;
    }

    /** 連不上、被限流或平台出錯，過一陣子再抓多半就好了，跟貼文刪除、轉私人分開處理 */
    public static function isTemporaryFailure(\Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError()));
    }

    /** Meta 的 CDN 網址用 oe 參數（16 進位的 Unix 時間）標失效時間；X 的圖網址沒有期限 */
    private function expiresAt(?string $url): ?Carbon
    {
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        return isset($query['oe']) && ctype_xdigit($query['oe'])
            ? Carbon::createFromTimestamp(hexdec($query['oe']), config('app.timezone'))
            : null;
    }

    private function youtube(array $post): array
    {
        $id = SocialUrl::youtubeId($post['canonical_url']);
        $data = $this->oembed('https://www.youtube.com/oembed', "https://www.youtube.com/watch?v={$id}");

        return array_merge($post, [
            'youtube_id' => $id,
            'author' => $data['author_name'] ?? null,
            'text' => $data['title'] ?? null,
        ]);
    }

    private function instagram(array $post): array
    {
        $this->oembed('https://graph.facebook.com/v25.0/instagram_oembed', $post['canonical_url']);
        $og = $this->ogTags($post['canonical_url']);

        if (str_contains($og['url'] ?? '', '/reel/')) {
            $post['kind'] = 'Reels';
        }

        // 描述是「（讚數 - ）帳號 on 日期: "內文".」，讚數不一定有；標題只有顯示名稱，補上帳號跟 Threads 一致
        $description = $og['description'] ?? '';
        $byline = preg_match('~(?:^|\s-\s)([A-Za-z0-9_.]+) on [A-Z][a-z]+ \d{1,2}, \d{4}:\s*"?(.*?)"?\.?\s*$~s', $description, $d);
        $author = $this->authorFromTitle($og['title'] ?? '');
        if ($byline && $author !== $d[1]) {
            $author = trim("{$author} (@{$d[1]})");
        }

        return array_merge($post, [
            'author' => $author,
            'text' => $byline ? $d[2] : ($description ?: null),
            'cover_url' => $og['image'] ?? null,
        ]);
    }

    private function threads(array $post): array
    {
        $this->oembed('https://graph.threads.net/v1.0/oembed', $post['canonical_url']);
        $og = $this->ogTags($post['canonical_url']);

        return array_merge($post, [
            'author' => $this->authorFromTitle($og['title'] ?? ''),
            'text' => $og['description'] ?? null,
            'cover_url' => $og['image'] ?? null,
        ]);
    }

    /**
     * X 的作者與內文用官方 oEmbed；預覽圖取自頁面的 og:image。純文字貼文給的是 X 自己的標誌圖，當作沒有圖。
     * X 的 og:type 一律是 article、也沒有 og:video，只能從縮圖網址判斷影片：影片與 GIF 的縮圖路徑含 video_thumb。
     * 頁面讀不到不影響卡片，退回文字卡；暫時的錯誤往外丟，免得原本存好的預覽圖被清掉。
     */
    private function x(array $post): array
    {
        $data = $this->oembed('https://publish.twitter.com/oembed', str_replace('https://x.com/', 'https://twitter.com/', $post['canonical_url']));

        try {
            $cover = $this->ogTags($post['canonical_url'])['image'] ?? null;
        } catch (\Throwable $e) {
            if (self::isTemporaryFailure($e)) {
                throw $e;
            }

            Log::error('X preview tags fetch failed', ['url' => $post['canonical_url'], 'exception' => $e->getMessage()]);
            $cover = null;
        }

        if ($cover !== null && SocialUrl::isXDefaultImage($cover)) {
            $cover = null;
        }

        return array_merge($post, [
            'kind' => str_contains((string) parse_url((string) $cover, PHP_URL_PATH), 'video_thumb') ? '影片' : $post['kind'],
            'author' => $data['author_name'] ?? null,
            'text' => html_entity_decode(strip_tags(explode('</p>', $data['html'])[0])),
            'cover_url' => $cover,
        ]);
    }

    private function facebook(array $post): array
    {
        $og = $this->ogTags($post['canonical_url']);
        // Reels 與影片的標題是「觀看數 · 心情數 | 內文 | 粉專名稱」，作者取最後一段；一般貼文的標題就是粉專名稱
        $title = $og['title'] ?? '';
        if (preg_match('~^\S+ views\b.* \| ([^|]+)$~su', $title, $m)) {
            $title = $m[1];
        }

        return array_merge($post, [
            'author' => $this->authorFromTitle($title),
            'text' => $og['description'] ?? null,
            'cover_url' => $og['image'] ?? null,
        ]);
    }

    private function authorFromTitle(string $title): ?string
    {
        $author = trim(preg_replace('~\s+on\s+(Instagram|Threads|Facebook)\b.*$~s', '', $title));

        return $author === '' ? null : $author;
    }

    /** @return array<string, string> */
    private function ogTags(string $url): array
    {
        $html = $this->get($url, 'en-US');
        preg_match_all('~<meta property="og:(\w+)" content="([^"]*)"~', $html, $matches, PREG_SET_ORDER);

        $tags = [];
        foreach ($matches as [, $name, $content]) {
            $tags[$name] ??= html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
        }

        return $tags;
    }

    private function oembed(string $endpoint, string $url): array
    {
        $data = json_decode($this->get($endpoint.'?'.http_build_query(['url' => $url])), true);

        if (! is_array($data)) {
            throw new RuntimeException("oEmbed 回傳不是 JSON：{$url}");
        }

        return $data;
    }

    /** 分享短網址：追轉址拿正式網址，拿不到就看頁面的 og:url */
    private function resolveRedirect(string $url): string
    {
        $response = $this->client('en-US')->get($url);
        $effective = (string) $response->effectiveUri();

        if (SocialUrl::canonical($effective) !== null) {
            return $effective;
        }

        preg_match('~<meta property="og:url" content="([^"]+)"~', $response->body(), $m);

        return isset($m[1]) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : $url;
    }

    private function get(string $url, string $language = 'zh-TW,zh;q=0.9'): string
    {
        return $this->client($language)->get($url)->body();
    }

    /** 只連已知平台主機，轉址也逐次檢查，避免被導去內部網址 */
    private function client(string $language): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept-Language' => $language])
            ->timeout(20)
            ->throw()
            ->beforeSending(function (Request $request) {
                if (! SocialUrl::hostMatches($request->url(), SocialUrl::fetchHosts())) {
                    throw new InvalidArgumentException('不允許的主機：'.parse_url($request->url(), PHP_URL_HOST));
                }
            })
            ->withOptions([
                'allow_redirects' => [
                    'max' => 5,
                    'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri) {
                        if (! SocialUrl::hostMatches((string) $uri, SocialUrl::fetchHosts())) {
                            throw new InvalidArgumentException("轉址到不允許的主機：{$uri->getHost()}");
                        }
                    },
                ],
                'on_headers' => function (ResponseInterface $response) {
                    if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                        throw new RuntimeException('回應太大');
                    }
                },
            ]);
    }
}
