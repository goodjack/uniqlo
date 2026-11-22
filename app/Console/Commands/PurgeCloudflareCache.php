<?php

namespace App\Console\Commands;

use App\Services\CloudflareCache;
use Illuminate\Console\Command;

/**
 * 依標籤清掉 Cloudflare 上的頁面快取。每日排程跑完後會呼叫，新價格不用等快取過期。
 */
class PurgeCloudflareCache extends Command
{
    protected $signature = 'cloudflare:purge';

    protected $description = '清掉 Cloudflare 上的頁面快取';

    public function handle(CloudflareCache $cloudflareCache): int
    {
        return $cloudflareCache->purgeAllPages() ? self::SUCCESS : self::FAILURE;
    }
}
