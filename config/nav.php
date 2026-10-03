<?php

use App\Enums\ProductTag;

// 導覽資料，桌機選單、手機選單、頁尾與麵包屑共用

// 導覽列與頁尾顯示的版號。要跟更新日誌最新那篇的標題一致（PageSkeletonTest 會檢查）
$version = 'v4.2.0';

return [
    // 不屬於分組的單一入口。label 給導覽列（短名），title 給麵包屑與頁面標題
    'links' => [
        'categories' => [
            'route' => 'categories.index',
            'label' => '分類',
            'title' => '商品分類',
            'icon' => 'sitemap',
        ],
        'favorites' => [
            'route' => 'favorites.index',
            'label' => '收藏',
            'title' => '收藏',
            'icon' => 'heart outline',
        ],
        // 不能指 search.show：它的參數必填，Breadcrumb::link() 不帶參數呼叫 route()
        'search' => [
            'route' => 'search.index',
            'label' => '搜尋',
            'title' => '搜尋',
            'icon' => 'search',
        ],
        'changelog' => [
            'route' => 'pages.changelog',
            'version' => $version,
            'label' => "{$version} 更新日誌",
            'title' => '更新日誌',
        ],
    ],

    /*
     * 清單頁的分組，十個清單平舖會變成十個一樣重要的入口。
     *
     * pinned：桌機導覽列外層的一鍵入口（同 master），下拉裡就不重複列。
     * 有對應 ProductTag 的清單直接讀它的 label；「熱門」那組不是官方標籤，寫死。
     */
    'groups' => [
        '優惠' => [
            ['route' => 'lists.limited-offers', 'label' => ProductTag::LimitedOffer->label(), 'icon' => 'certificate', 'pinned' => true],
            ['route' => 'lists.sale', 'label' => ProductTag::Sale->label(), 'icon' => 'shopping basket', 'pinned' => true],
            ['route' => 'lists.multi-buy', 'label' => ProductTag::MultiBuy->label(), 'icon' => 'cubes'],
            ['route' => 'lists.online-special', 'label' => ProductTag::OnlineSpecial->label(), 'icon' => 'tv'],
        ],
        '熱門' => [
            ['route' => 'lists.most-visited', 'label' => '熱門瀏覽', 'icon' => 'chart line'],
            ['route' => 'lists.top-wearing', 'label' => '熱門穿搭', 'icon' => 'camera retro'],
            ['route' => 'lists.most-reviewed', 'label' => '熱門評論', 'icon' => 'comments outline'],
            ['route' => 'lists.japan-most-reviewed', 'label' => '日本熱門評論', 'icon' => 'comments outline'],
        ],
        '新品' => [
            ['route' => 'lists.new', 'label' => ProductTag::NewArrival->label(), 'icon' => 'leaf'],
            ['route' => 'lists.coming-soon', 'label' => ProductTag::ComingSoon->label(), 'icon' => 'checked calendar'],
        ],
    ],
];
