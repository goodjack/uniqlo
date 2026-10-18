<?php

namespace App\Console\Commands;

use App\Services\SocialPostService;
use Illuminate\Console\Command;

class SyncSocialPosts extends Command
{
    protected $signature = 'social-post:sync {--full : 全部重抓，不管 Notion 上有沒有改過}';

    protected $description = '把 Notion 的社群熱推清單同步到商品頁';

    public function handle(SocialPostService $socialPostService): int
    {
        $stats = $socialPostService->sync((bool) $this->option('full'));

        $this->info(sprintf(
            '社群熱推同步完成：抓取 %d、失敗 %d、沒變跳過 %d、移除 %d',
            $stats['fetched'],
            $stats['failed'],
            $stats['skipped'],
            $stats['removed'],
        ));

        return self::SUCCESS;
    }
}
