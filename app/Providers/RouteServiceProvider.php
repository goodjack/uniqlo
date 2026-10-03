<?php

namespace App\Providers;

use App\Models\HmallProduct;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 分類頁的關鍵字比對在大分類上會掃全表（跟 /search 同一種工作量），
        // 純瀏覽只是帶分頁的索引查詢，不需要限流。
        RateLimiter::for('category-search', function (Request $request) {
            return $request->filled('q')
                ? Limit::perMinute(30)->by($request->ip())
                : Limit::none();
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });

        Route::bind('uniqlo_product_code', function ($value) {
            return HmallProduct::query()
                ->where('brand', 'UNIQLO')
                ->where('product_code', $value)
                ->firstOrFail();
        });

        Route::bind('gu_product_code', function ($value) {
            return HmallProduct::query()
                ->where('brand', 'GU')
                ->where('product_code', $value)
                ->firstOrFail();
        });
    }
}
