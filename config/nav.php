<?php

/*
 * 導覽資料。桌機選單、手機選單與各頁的麵包屑共用這一份。
 *
 * 原本寫在 layouts/nav.blade.php 的 @php 區塊裡，只有導覽列讀得到；麵包屑要用
 * 同一組名稱與分組，再抄一份遲早會跟導覽列對不起來。
 */

return [
    /*
     * 不屬於任何分組的單一入口。
     *
     * label 給導覽列（橫向空間窄，用短名），title 給麵包屑與頁面標題
     * （單獨出現時要看得懂是什麼）。兩者相同時就寫成一樣的值。
     */
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
        'search' => [
            'route' => 'search.show',
            'label' => '搜尋',
            'title' => '搜尋',
            'icon' => 'search',
        ],
        /*
         * 更新日誌。master 的導覽列桌機與手機各有一個入口，標籤帶著版號；
         * 版號跟 pages/changelog.blade.php 最新那一篇綁在一起，改那頁要順手改這裡。
         */
        'changelog' => [
            'route' => 'pages.changelog',
            'label' => 'v4.1.0 更新日誌',
            'title' => '更新日誌',
        ],
    ],

    /*
     * 清單頁的分組。
     *
     * 十種清單頁其實是同一件事的不同切面（促銷狀態、熱門程度、上市時間），
     * 全部平舖在同一列會變成十個看起來一樣重要的入口。
     *
     * 分組名同時是清單頁麵包屑的第二層（首頁 › 優惠 › 期間限定），所以分組本身
     * 不能為了導覽列好看就拆掉。
     *
     * pinned 的項目在桌機導覽列會多一個外層的一鍵入口，那個分組的下拉裡就不再
     * 重複列它。期間限定與特價是這個站最常被點的兩個清單，master 也是把它們放在
     * 外層直達；手機選單維持整組列出，那裡本來就是攤開的清單。
     */
    'groups' => [
        '優惠' => [
            ['route' => 'lists.limited-offers', 'label' => '期間限定', 'icon' => 'certificate', 'pinned' => true],
            ['route' => 'lists.sale', 'label' => '特價', 'icon' => 'shopping basket', 'pinned' => true],
            ['route' => 'lists.multi-buy', 'label' => '合購', 'icon' => 'cubes'],
            ['route' => 'lists.online-special', 'label' => '網路獨家', 'icon' => 'tv'],
        ],
        '熱門' => [
            ['route' => 'lists.most-visited', 'label' => '熱門瀏覽', 'icon' => 'chart line'],
            ['route' => 'lists.top-wearing', 'label' => '熱門穿搭', 'icon' => 'camera retro'],
            ['route' => 'lists.most-reviewed', 'label' => '熱門評論', 'icon' => 'comments outline'],
            ['route' => 'lists.japan-most-reviewed', 'label' => '日本熱門評論', 'icon' => 'comments outline'],
        ],
        '新品' => [
            ['route' => 'lists.new', 'label' => '新品', 'icon' => 'leaf'],
            ['route' => 'lists.coming-soon', 'label' => '即將上市', 'icon' => 'checked calendar'],
        ],
    ],
];
