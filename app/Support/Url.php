<?php

namespace App\Support;

use App\Enums\Brand;

class Url
{
    /**
     * 分類頁的網址。code 來自官網，所有連到分類頁的地方都要走這裡。
     */
    public static function category(Brand $brand, string $code): string
    {
        return route('categories.show', [
            'brand' => $brand->slug(),
            'code' => self::segment($code),
        ]);
    }

    /**
     * 把使用者輸入或外部資料放進 route() 的路徑參數前先過這裡。
     *
     * Laravel 產生網址時刻意不編碼 / ? # %（RouteUrlGenerator::$dontEncode），
     * 這些字元原樣留在路徑裡會 404、被瀏覽器截斷或解碼成別的字。先編一次之後，
     * route() 再編的那一層只會把 %25 還原成 %，產生的網址剛好編碼一次。
     */
    public static function segment(string $value): string
    {
        return rawurlencode($value);
    }
}
