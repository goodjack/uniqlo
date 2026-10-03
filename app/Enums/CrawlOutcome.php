<?php

namespace App\Enums;

/**
 * 爬蟲的執行結果，值直接當指令的 exit code。完全失敗通常是被擋、要立刻處理；
 * 部分成功底下還分好幾種，要看 CrawlResult 附的說明。
 */
enum CrawlOutcome: int
{
    case Succeeded = 0;
    case Failed = 1;
    case PartiallySucceeded = 2;

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => '成功',
            self::Failed => '完全失敗',
            self::PartiallySucceeded => '部分成功',
        };
    }
}
