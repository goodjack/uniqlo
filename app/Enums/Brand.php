<?php

namespace App\Enums;

/**
 * 值是資料庫裡存的字串，網址用小寫的 slug。
 */
enum Brand: string
{
    case Uniqlo = 'UNIQLO';
    case Gu = 'GU';

    public function slug(): string
    {
        return strtolower($this->value);
    }

    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom(strtoupper($slug));
    }
}
