<?php

namespace App\Support;

/**
 * 站內各處搜尋共用的切詞：用空白切（含手機輸入法常帶到的全形空白），
 * 每個詞都要命中才算符合。
 */
class Keywords
{
    /**
     * @return array<int, string>
     */
    public static function split(string $query): array
    {
        return preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
