<?php

namespace App\Http\Controllers;

use App\Models\SocialPost;
use App\Services\SocialPostFetcher;
use App\Support\SocialUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 轉送社群熱推卡片的預覽圖：伺服器不存檔，交給 CDN 依 Cache-Control 快取，
 * 訪客的瀏覽器也不會直接連到 Meta 或 X。只轉送資料庫裡現有貼文的圖，不能拿來代抓任意網址。
 */
class SocialPostImageController extends Controller
{
    private const CACHE_SECONDS = 7 * 24 * 3600;

    // 只放行點陣圖；SVG 能夾帶程式碼，從自己網域送出等於讓外部內容在本站執行
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

    public function show(SocialPost $socialPost): Response
    {
        $url = $socialPost->cover_url;
        abort_unless($socialPost->status === 'ok' && $url && SocialUrl::hostMatches($url, SocialUrl::COVER_HOSTS), 404);

        try {
            $upstream = Http::withHeaders(['User-Agent' => SocialPostFetcher::USER_AGENT])
                ->withOptions(['allow_redirects' => false])
                ->timeout(10)
                ->get($url);
        } catch (\Throwable $e) {
            Log::error('Social post image proxy failed', ['social_post_id' => $socialPost->id, 'exception' => $e->getMessage()]);
            $upstream = null;
        }

        $type = strtolower(trim(explode(';', $upstream?->header('Content-Type') ?? '')[0]));
        if (! $upstream?->successful() || ! in_array($type, self::IMAGE_TYPES, true)) {
            // 網址過期等下次同步重抓；短暫快取，別讓每個訪客都打回來
            return response('', 404, ['Cache-Control' => 'public, max-age=300']);
        }

        return response($upstream->body(), 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
