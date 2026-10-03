<?php

namespace App\Support;

/**
 * 寫入一頁商品的結果。知道編號的失敗可以從缺貨判定排除；拿不到編號的排除不了，
 * 只能整輪不做缺貨判定。
 */
final class ProductSaveResult
{
    /**
     * @param  array<int, string>  $failedProductCodes  寫入失敗、而且知道商品編號的那幾件
     * @param  int  $unidentifiedFailureCount  寫入失敗、連商品編號都拿不到的筆數
     */
    public function __construct(
        public readonly array $failedProductCodes = [],
        public readonly int $unidentifiedFailureCount = 0,
    ) {}

    public function hasFailures(): bool
    {
        return $this->failedProductCodes !== [] || $this->unidentifiedFailureCount > 0;
    }
}
