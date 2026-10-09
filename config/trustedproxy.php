<?php

return [

    // 額外信任的代理，只給本機模擬用，見 .env.example 的 TRUSTED_PROXIES_EXTRA
    'extra_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES_EXTRA', ''))
    ))),

];
