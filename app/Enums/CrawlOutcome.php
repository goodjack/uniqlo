<?php

namespace App\Enums;

/**
 * 爬蟲的執行結果，值直接當指令的 exit code。完全失敗通常是被擋、要立刻處理；
 * 部分成功底下還分好幾種，要看 CrawlResult 附的說明。缺貨判定逾期是部分成功
 * 連續太多天，資料雖然有更新，但下架商品一直沒被標出來，要人處理。
 */
enum CrawlOutcome: int
{
    case Succeeded = 0;
    case Failed = 1;
    case PartiallySucceeded = 2;
    case StockoutOverdue = 3;

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => '成功',
            self::Failed => '完全失敗',
            self::PartiallySucceeded => '部分成功',
            self::StockoutOverdue => '缺貨判定逾期',
        };
    }

    /** 要發紅色失敗通知的結果 */
    public function needsAttention(): bool
    {
        return $this === self::Failed || $this === self::StockoutOverdue;
    }
}
