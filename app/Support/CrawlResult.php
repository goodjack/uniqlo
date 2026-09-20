<?php

namespace App\Support;

use App\Enums\CrawlOutcome;

/**
 * 爬一輪的結果：結果本身，加一句人看得懂的說明。
 *
 * 說明是給通知用的。exit code 只分得出成功、部分成功、完全失敗，但「部分成功」
 * 底下有好幾種狀況，處理的急迫程度不一樣：目錄有缺頁代表今天沒做缺貨判定，
 * 排除幾件寫入失敗的商品之後做了缺貨判定則是另一回事。只看一句「部分成功」
 * 分不出來，要看 log 才知道發生什麼事。
 */
final class CrawlResult
{
    public function __construct(
        public readonly CrawlOutcome $outcome,
        public readonly ?string $note = null,
    ) {}

    /**
     * 通知與 console 用的一行描述，例如「部分成功：目錄有缺頁，未執行缺貨判定」。
     */
    public function describe(): string
    {
        if ($this->note === null) {
            return $this->outcome->label();
        }

        return "{$this->outcome->label()}：{$this->note}";
    }
}
