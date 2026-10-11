<?php

namespace App\Support;

/**
 * 對外標題與說明接在後面的站名。改格式只動這裡。
 */
final class SiteTitle
{
    public const NAME = 'UQ 搜尋';

    /** 頁面標題、社群分享標題：「頁名 | UQ 搜尋」 */
    public static function append(string $text): string
    {
        return $text.' | '.self::NAME;
    }

    /** 分享文字與說明：「內容 | UNIQLO 比價 | UQ 搜尋」，多帶一句讓不認識站名的人知道在做什麼 */
    public static function appendWithTagline(string $text): string
    {
        return self::append($text.' | UNIQLO 比價');
    }
}
