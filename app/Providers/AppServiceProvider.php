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
        // 排程步驟留給彙整通知的說明文字。指令寫、AppSchedule 讀，兩邊必須拿到同一份。
        $this->app->singleton(TaskNotes::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // 框架預設用 input() 讀頁碼，GET 帶 JSON 內文時會讀到內文；分頁連結、
        // canonical 與篩選都只認網址參數，頁碼也要讀同一份
        Paginator::currentPageResolver(function ($pageName = 'page') {
            $page = request()->query($pageName);

            return filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1 ? (int) $page : 1;
        });

        if (config('app.force_https')) {
            \URL::forceScheme('https');
        }
    }
}
