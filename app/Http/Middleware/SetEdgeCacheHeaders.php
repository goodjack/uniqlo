<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 把公開頁面標成 CDN 可以快取，減輕原始伺服器負擔、加快頁面載入。
 *
 * routes/web.php 裡掛了 edge-cache 的路由就是全部的可快取頁面，頁面上不能有
 * 個人化內容。Cloudflare 後台快取規則的路徑條件要跟這份範圍一致，改這邊要一起改那邊。
 *
 * 不用 Laravel 內建的 cache.headers：它連 404、500 也會標成可快取。
 */
class SetEdgeCacheHeaders
{
    /** 瀏覽器可以直接沿用的秒數。清 CDN 快取清不到瀏覽器，所以要短 */
    public const MAX_AGE = 300;

    /** CDN 可以存的秒數，資料更新後最晚這麼久會反映。對齊每小時的社群熱推同步 */
    public const S_MAXAGE = 3600;

    /** 清快取只清這個標籤，圖片、樣式這些 CDN 快取不受影響 */
    public const CACHE_TAG = 'pages';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 帶 cookie 的回應寧可不快取，否則某個人的 cookie 會被 CDN 發給所有人。
        // 帶內容的請求也不快取：CDN 的快取鍵只看網址，Laravel 卻會把請求內容當成輸入讀進去
        if ($response->getStatusCode() === Response::HTTP_OK
            && $response->headers->getCookies() === []
            && $request->getContent() === '') {
            $response->setPublic();
            $response->setMaxAge(self::MAX_AGE);
            $response->setSharedMaxAge(self::S_MAXAGE);
            $response->headers->set('Cache-Tag', self::CACHE_TAG);
        }

        return $response;
    }
}
