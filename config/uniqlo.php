<?php

return [

    'api' => [
        'style_book_list' => env('UQ_API_STYLING_BOOK_LIST'),
        'style_book_detail' => env('UQ_API_STYLING_BOOK_DETAIL'),
        'hmall_search' => env('UQ_API_HMALL_SEARCH'),
        'spu' => env('UQ_API_SPU'),

        'style_hint_list' => [
            'jp' => env('UQ_API_STYLE_HINT_LIST_JP'),
            'us' => env('UQ_API_STYLE_HINT_LIST_US'),
        ],
        'style_hint_detail' => [
            'jp' => env('UQ_API_STYLE_HINT_DETAIL_JP'),
            'us' => env('UQ_API_STYLE_HINT_DETAIL_US'),
        ],

        'ugc_product_id_contents' => [
            'tw' => env('UQ_API_UGC_PRODUCT_ID_CONTENTS_TW'),
        ],
    ],

    'data' => [
        'hmall_search' => [
            'url' => env('UQ_DATA_HMALL_SEARCH_URL'),
            'description' => env('UQ_DATA_HMALL_SEARCH_DESCRIPTION'),
        ],
    ],

];
