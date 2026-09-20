<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 額外信任的代理 IP（僅供本機測試用）
    |--------------------------------------------------------------------------
    |
    | 正式站的信任清單固定寫在 App\Http\Middleware\TrustProxies（Cloudflare
    | 官方公布的 IP 範圍），不受這個設定影響。本機沒有 Cloudflare，要模擬
    | 「代理已經在 X-Forwarded-For 加上真實 IP」的情境時，才把本機的連線來源
    | （例如用 curl 打自己時通常是 127.0.0.1）填進 TRUSTED_PROXIES_EXTRA
    | 這個環境變數（逗號分隔多個值），讓 Laravel 願意信任這個來源、去讀
    | X-Forwarded-For。正式站不要設這個變數。
    |
    */

    'extra_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES_EXTRA', ''))
    ))),

];
