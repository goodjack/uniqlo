<?php

namespace App\Support;

/**
 * 知道編號的寫入失敗可以從缺貨判定排除；拿不到編號的排除不了，只能整輪不做缺貨判定。
 */
final class ProductSaveResult
{
    /** @param  array<int, string>  $failedProductCodes  台灣是商品編號、日本是 l1Id */
    public function __construct(
        public readonly array $failedProductCodes = [],
        public readonly int $unidentifiedFailureCount = 0,
    ) {}

    public function hasFailures(): bool
    {
        return $this->failedProductCodes !== [] || $this->unidentifiedFailureCount > 0;
    }
}
