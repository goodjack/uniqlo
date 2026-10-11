<?php

namespace App\Providers;

use App\Support\TaskNotes;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 指令寫、AppSchedule 讀，兩邊必須拿到同一份
        $this->app->singleton(TaskNotes::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // 預設用 input() 讀頁碼會讀到 JSON 內文；分頁連結與篩選都只認網址參數
        Paginator::currentPageResolver(function ($pageName = 'page') {
            $page = request()->query($pageName);

            return filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1 ? (int) $page : 1;
        });

        if (config('app.force_https')) {
            \URL::forceScheme('https');
        }
    }
}
