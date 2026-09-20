<?php

namespace App\Providers;

use App\Support\TaskNotes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 排程步驟留給彙整通知的說明文字。指令寫、AppSchedule 讀，兩邊必須拿到同一份。
        $this->app->singleton(TaskNotes::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if (config('app.force_https')) {
            \URL::forceScheme('https');
        }
    }
}
