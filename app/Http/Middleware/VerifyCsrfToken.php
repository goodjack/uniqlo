<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // 只拿商品編號換卡片、不改任何資料，沒有 CSRF 要防；豁免後頁面停留
        // 超過 session 壽命也不會因為 token 過期而載入失敗
        'favorites/cards',
    ];
}
