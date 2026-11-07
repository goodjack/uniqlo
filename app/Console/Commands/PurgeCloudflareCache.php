<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 清掉 Cloudflare 上整個網域的頁面快取（免費方案能用的 purge everything）。
 * 每日排程跑完後會呼叫，新價格不用等快取過期。
 */
class PurgeCloudflareCache extends Command
{
    protected $signature = 'cloudflare:purge';

    protected $description = '清掉 Cloudflare 上整個網域的頁面快取';

    /**
     * 沒設定就略過、算成功：本機與還沒設定的環境，排程照常跑完。
     * 清除失敗只記 log 回傳 FAILURE，不丟例外，頂多等快取自然過期。
     */
    public function handle(): int
    {
        $zoneId = config('services.cloudflare.zone_id');
        $token = config('services.cloudflare.purge_token');

        if (blank($zoneId) || blank($token)) {
            Log::info('Skipped CDN cache purge because Cloudflare is not configured');

            return self::SUCCESS;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", ['purge_everything' => true]);
        } catch (ConnectionException $e) {
            Log::error('CDN cache purge request failed', ['zone_id' => $zoneId, 'exception' => $e]);

            return self::FAILURE;
        }

        if ($response->successful() && $response->json('success') === true) {
            Log::info('Purged CDN cache', ['zone_id' => $zoneId]);

            return self::SUCCESS;
        }

        Log::error('CDN cache purge was rejected', [
            'zone_id' => $zoneId,
            'status' => $response->status(),
            'errors' => $response->json('errors'),
        ]);

        return self::FAILURE;
    }
}
