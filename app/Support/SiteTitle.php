<?php

namespace App\Support;

final class SiteTitle
{
    public const NAME = 'UQ 搜尋';

    public const TAGLINE = 'UNIQLO 比價';

    public static function append(string $text): string
    {
        return $text.' | '.self::NAME;
    }

    /** 多帶一句，讓不認識站名的人知道這站在做什麼 */
    public static function appendWithTagline(string $text): string
    {
        return self::append($text.' | '.self::TAGLINE);
    }
}
