<?php

namespace App\Support;

/**
 * 寫入一頁商品的結果。
 *
 * 失敗要分兩種回報，因為缺貨判定對它們的處理方式不同：知道商品編號的失敗，代表
 * 那件商品今天在來源其實還在、只是資料沒寫進資料庫，缺貨判定把它排除掉就不會被
 * 冤枉標成下架；連商品編號都拿不到的失敗沒辦法排除，只能整輪不做缺貨判定。
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

    public function failureCount(): int
    {
        return count($this->failedProductCodes) + $this->unidentifiedFailureCount;
    }
}
