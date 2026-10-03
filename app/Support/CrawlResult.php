<?php

namespace App\Support;

use App\Enums\CrawlOutcome;

/**
 * 爬一輪的結果，加一句給通知看的說明。
 *
 * 同樣是部分成功，「目錄有缺頁、今天沒做缺貨判定」跟「排除幾件寫入失敗的商品後
 * 做了缺貨判定」急迫程度不同，只看 exit code 分不出來。
 */
final class CrawlResult
{
    public function __construct(
        public readonly CrawlOutcome $outcome,
        public readonly ?string $note = null,
    ) {}

    /** 例如「部分成功：未執行缺貨判定，目錄有缺頁」 */
    public function describe(): string
    {
        if ($this->note === null) {
            return $this->outcome->label();
        }

        return "{$this->outcome->label()}：{$this->note}";
    }
}
