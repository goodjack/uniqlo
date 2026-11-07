<?php

// 掃吊牌條碼找商品。查詢網址與標頭在 uniqlo.php、gu.php 的 api.sku_lookup 與 api.sku_lookup_headers

return [

    // 總開關。關掉時搜尋框的條碼圖示不出現、查詢網址回 404；官網介面改掉或被擋時用。
    // 掃描入口跟著頁面一起輸出，頁面可能被 CDN 快取，切換後要清一次 CDN 快取才會立即生效
    'enabled' => (bool) env('BARCODE_LOOKUP_ENABLED', false),

    'limits' => [
        // 同一個來源每分鐘最多查幾次條碼（含資料庫與快取命中）
        'per_minute' => (int) env('BARCODE_LOOKUP_PER_MINUTE', 10),
        // 全站每天最多向官網送幾次請求；一個條碼最多依序問四次
        'daily_upstream' => (int) env('BARCODE_LOOKUP_DAILY_UPSTREAM', 400),
        // 同一個來源每天最多讓網站向官網送幾次請求，約 10 到 20 個新條碼；查表、快取命中不算
        'daily_upstream_per_client' => (int) env('BARCODE_LOOKUP_DAILY_UPSTREAM_PER_CLIENT', 40),
        // 官網出錯（被擋、太多請求、連不上、逾時）後幾分鐘內不再問官網，直接回暫時無法查詢；查不到不算出錯
        'upstream_cooldown_minutes' => (int) env('BARCODE_LOOKUP_UPSTREAM_COOLDOWN_MINUTES', 10),
    ],

];
