<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

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
        // 鎖留 36 小時：中途被強制中止時最多擋住隔天那一輪
        // Discord 通知要排程跑起來才會發；排程沒啟動或跑到一半中斷，只能靠外部發現。
        // 完成不論成敗都回報：失敗由 Discord 通知，這裡只確認有跑完
        $heartbeat = config('app.schedule_heartbeat');
        $schedule->command('app:schedule')->dailyAt('09:01')->withoutOverlapping(2160)
            ->pingBeforeIf(filled($heartbeat['start_url']), (string) $heartbeat['start_url'])
            ->thenPingIf(filled($heartbeat['finish_url']), (string) $heartbeat['finish_url']);

        $schedule->command('hmall-product:cache-most-visited')
            ->everyTwoHours()
            ->withoutOverlapping();

        // 平常只處理 Notion 上新加或改過的；定期整批重抓，發現刪文、轉私人
        $schedule->command('social-post:sync')->hourlyAt(17)->withoutOverlapping();
        $schedule->command('social-post:sync --full')->dailyAt('04:37')->withoutOverlapping();
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
