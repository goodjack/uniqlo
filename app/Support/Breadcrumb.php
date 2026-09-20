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
}
