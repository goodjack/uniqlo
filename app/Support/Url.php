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

    public static function officialTaiwanProduct(Brand $brand, string $productCode): string
    {
        return self::officialHost($brand).'/tw/zh_TW/product-detail.html?productCode='.rawurlencode($productCode);
    }

    public static function officialJapanProduct(Brand $brand, string $productId): string
    {
        return self::officialHost($brand).'/jp/ja/products/'.rawurlencode($productId);
    }

    public static function officialJapanSearch(Brand $brand, string $code): string
    {
        return self::officialHost($brand).'/jp/ja/search?q='.rawurlencode($code);
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

    private static function officialHost(Brand $brand): string
    {
        return $brand === Brand::Gu ? 'https://www.gu-global.com' : 'https://www.uniqlo.com';
    }
}
