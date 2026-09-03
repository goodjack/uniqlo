<?php

namespace App\Support;

/**
 * 麵包屑的組裝。導覽資料本身在 config/nav.php，這裡只負責查詢與拼接。
 *
 * 每一層是 ['label' => string, 'url' => ?string]，url 是 null 的那幾層只當文字
 * （導覽的分組名、沒有自己頁面的頂層分類都是這種）。輸出直接餵給
 * resources/views/partials/breadcrumb.blade.php。
 */
class Breadcrumb
{
    /**
     * 首頁那一層。除了首頁自己以外，每一頁的麵包屑都從它開始。
     *
     * @return array{label: string, url: string}
     */
    public static function home(): array
    {
        return ['label' => '首頁', 'url' => route('home')];
    }

    /**
     * 只當文字、點不下去的一層。
     *
     * @return array{label: string, url: null}
     */
    public static function text(string $label): array
    {
        return ['label' => $label, 'url' => null];
    }

    /**
     * config/nav.php 的 links 裡某個單一入口那一層（商品分類、收藏）。
     *
     * @return array{label: string, url: string}
     */
    public static function link(string $key): array
    {
        $link = config("nav.links.{$key}");

        return ['label' => $link['title'], 'url' => route($link['route'])];
    }

    /**
     * 目前這條清單頁在導覽裡的位置：分組名加清單名（優惠 › 期間限定）。
     *
     * 分組沒有自己的頁面，所以只當文字；清單名是當頁，交給 blade 標
     * aria-current。認不出來（例如新增的清單頁還沒登錄到 config/nav.php）
     * 時回空陣列，呼叫端的麵包屑就只剩首頁那一層，不會壞掉。
     *
     * @return array<int, array{label: string, url: ?string}>
     */
    public static function currentList(): array
    {
        foreach (config('nav.groups') as $group => $items) {
            foreach ($items as $item) {
                if (request()->routeIs($item['route'])) {
                    return [self::text($group), self::text($item['label'])];
                }
            }
        }

        return [];
    }
}
