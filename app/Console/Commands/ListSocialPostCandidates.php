<?php

namespace App\Console\Commands;

use App\Models\HmallProduct;
use App\Services\SocialPostService;
use Illuminate\Console\Command;

class ListSocialPostCandidates extends Command
{
    protected $signature = 'social-post:candidates';

    protected $description = '列出有社群人氣話題標籤、還沒收錄社群熱推貼文的在售商品';

    public function handle(SocialPostService $socialPostService): int
    {
        $hmallProducts = $socialPostService->getCommunityTopicsWithoutPosts();

        if ($hmallProducts->isEmpty()) {
            $this->info('有社群人氣話題標籤的在售商品都已經有貼文');

            return self::SUCCESS;
        }

        $this->table(
            ['品牌', '貨號', '名稱', '評論數', '商品頁'],
            $hmallProducts->map(fn (HmallProduct $hmallProduct) => [
                $hmallProduct->brand,
                $hmallProduct->code,
                $hmallProduct->name,
                $hmallProduct->evaluation_count,
                $hmallProduct->route_url,
            ])
        );

        $this->info("共 {$hmallProducts->count()} 件");

        return self::SUCCESS;
    }
}
