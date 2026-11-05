<?php

return [

    'api' => [
        'hmall_search' => env('GU_API_HMALL_SEARCH'),
        'spu' => env('GU_API_SPU'),

        'product_list' => [
            'jp' => env('GU_API_PRODUCT_LIST_JP'),
        ],

        'ugc_style_hint_list' => [
            'tw' => env('GU_API_UGC_STYLE_HINT_LIST_TW'),
        ],

        'ugc_official_style_list' => [
            'tw' => env('GU_API_UGC_OFFICIAL_STYLE_LIST_TW'),
        ],

        // 用吊牌條碼查商品，網址裡的 {barcode} 換成條碼
        'sku_lookup' => [
            'tw' => env('GU_API_SKU_LOOKUP_TW'),
            'jp' => env('GU_API_SKU_LOOKUP_JP'),
        ],

        // 查條碼時要帶的標頭，JSON 物件字串，例如 {"Accept-Language":"zh-TW"}；解析失敗當成沒有
        'sku_lookup_headers' => [
            'tw' => env('GU_SKU_LOOKUP_HEADERS_TW'),
            'jp' => env('GU_SKU_LOOKUP_HEADERS_JP'),
        ],

        'v3' => [
            'search' => [
                'tw' => env('GU_API_V3_SEARCH_TW'),
            ],
            'description' => [
                'tw' => env('GU_API_V3_DESCRIPTION_TW'),
            ],
        ],
    ],

];
