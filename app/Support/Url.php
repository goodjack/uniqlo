<?php

namespace App\Support;

use App\Enums\Brand;

class Url
{
    public static function category(Brand $brand, string $code): string
    {
        return route('categories.show', [
            'brand' => $brand->slug(),
            'code' => self::segment($code),
        ]);
    }

    /**
     * 使用者輸入或外部資料放進 route() 路徑參數前先過這裡：Laravel 刻意不編碼
     * / ? # %（RouteUrlGenerator::$dontEncode），先編一次，route() 那層只會把 %25
     * 還原成 %，結果剛好編碼一次。
     */
    public static function segment(string $value): string
    {
        return rawurlencode($value);
    }
}
