<?php

namespace App\Enums;

/**
 * 站上收錄的兩個品牌。
 *
 * 值就是資料庫裡存的字串。網址用小寫的 slug：/categories/uniqlo/all_men-tops
 * 讀起來比 /categories/UNIQLO/... 自然，也符合網址慣例。
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
