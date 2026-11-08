<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // TODO: 等現有排程結束後再開啟
        // $schedule->command('app:style-hint-schedule')->dailyAt('01:01')->withoutOverlapping(2880);
        // 爬蟲會續跑與重試，一輪可能超過一天；兩輪同時跑會共用同一份續跑進度。
        // 鎖留 36 小時：中途被強制中止時最多擋住隔天那一輪。
        // 開始與跑完各回報一次給外部監控，排程沒啟動或中斷時才發現得了；跑完不論成敗都回報
        $heartbeat = config('app.schedule_heartbeat');
        $schedule->command('app:schedule')->dailyAt('09:01')->withoutOverlapping(2160)
            ->before(fn () => self::ping($heartbeat['start_url']))
            ->then(fn () => self::ping($heartbeat['finish_url']));

        $schedule->command('hmall-product:cache-most-visited')
            ->everyTwoHours()
            ->withoutOverlapping();

        // 平常只處理 Notion 上新加或改過的；定期整批重抓，發現刪文、轉私人
        $schedule->command('social-post:sync')->hourlyAt(17)->withoutOverlapping();
        $schedule->command('social-post:sync --full')->dailyAt('04:37')->withoutOverlapping();
    }

    /**
     * 監控服務的狀況不能拖住排程：設等待上限，失敗只記 log。
     */
    private static function ping(?string $url): void
    {
        if (blank($url)) {
            return;
        }

        try {
            Http::connectTimeout(5)->timeout(10)->get($url);
        } catch (ConnectionException $e) {
            Log::error('Schedule heartbeat ping failed', ['exception' => $e]);
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
