<?php

namespace App\Support;

/**
 * 麵包屑的每一層：['label' => string, 'url' => ?string]，url 為 null 時只當文字。
 * 導覽資料在 config/nav.php。
 */
class Breadcrumb
{
    /**
     * @return array{label: string, url: string}
     */
    public static function home(): array
    {
        return ['label' => '首頁', 'url' => route('home')];
    }

    /**
     * @return array{label: string, url: null}
     */
    public static function text(string $label): array
    {
        return ['label' => $label, 'url' => null];
    }

    /**
     * config/nav.php 的 links 裡的單一入口。
     *
     * @return array{label: string, url: string}
     */
    public static function link(string $key): array
    {
        $link = config("nav.links.{$key}");

        return ['label' => $link['title'], 'url' => route($link['route'])];
    }
}
