<?php

// 掃吊牌條碼找商品。查詢網址在 uniqlo.php、gu.php 的 api.sku_lookup

return [

    // 總開關。關掉時搜尋框的條碼圖示不出現、查詢網址回 404；官網介面改掉或被擋時用
    'enabled' => (bool) env('BARCODE_LOOKUP_ENABLED', false),

    // 問官網時要帶的標頭，JSON 物件字串，例如 {"Accept-Language":"zh-TW"}；解析失敗當成沒有
    'headers' => [
        'tw' => env('SKU_LOOKUP_HEADERS_TW'),
        'jp' => env('SKU_LOOKUP_HEADERS_JP'),
    ],

    'limits' => [
        // 同一個來源每分鐘最多查幾次條碼（含資料庫與快取命中）
        'per_minute' => (int) env('BARCODE_LOOKUP_PER_MINUTE', 10),
        // 全站每天最多向官網送幾次請求；一個條碼最多依序問四次
        'daily_upstream' => (int) env('BARCODE_LOOKUP_DAILY_UPSTREAM', 400),
        // 同一個來源每天最多讓網站向官網送幾次請求，約 10 到 20 個新條碼；查表、快取命中不算
        'daily_upstream_per_client' => (int) env('BARCODE_LOOKUP_DAILY_UPSTREAM_PER_CLIENT', 40),
    ],

];
