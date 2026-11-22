<?php

namespace App\Services;

use App\Http\Middleware\SetEdgeCacheHeaders;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 清 Cloudflare 上的頁面快取。沒設定就略過、算成功：本機與還沒設定的環境照常跑完。
 * 清不掉只記 log 回傳 false，不丟例外，頂多等快取自然過期。
 */
class CloudflareCache
{
    /** 一次請求最多幾個網址 */
    public const MAX_URLS_PER_REQUEST = 30;

    public function purgeAllPages(): bool
    {
        return $this->purge(['tags' => [SetEdgeCacheHeaders::CACHE_TAG]]);
    }

    /**
     * @param  array<int, string>  $urls
     */
    public function purgeUrls(array $urls): bool
    {
        $succeeded = true;

        foreach (array_chunk(array_values(array_unique($urls)), self::MAX_URLS_PER_REQUEST) as $chunk) {
            $succeeded = $this->purge(['files' => $chunk]) && $succeeded;
        }

        return $succeeded;
    }

    private function purge(array $payload): bool
    {
        $zoneId = config('services.cloudflare.zone_id');
        $token = config('services.cloudflare.purge_token');

        if (blank($zoneId) || blank($token)) {
            Log::info('Skipped CDN cache purge because Cloudflare is not configured');

            return true;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", $payload);
        } catch (ConnectionException $e) {
            Log::error('CDN cache purge request failed', ['zone_id' => $zoneId, 'exception' => $e]);

            return false;
        }

        if ($response->successful() && $response->json('success') === true) {
            Log::info('Purged CDN cache', ['zone_id' => $zoneId, 'purged' => array_key_first($payload)]);

            return true;
        }

        Log::error('CDN cache purge was rejected', [
            'zone_id' => $zoneId,
            'status' => $response->status(),
            'errors' => $response->json('errors'),
        ]);

        return false;
    }
}
