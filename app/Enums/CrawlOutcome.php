<?php

namespace App\Enums;

/** 值直接當指令的 exit code */
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

    public function needsAttention(): bool
    {
        return $this === self::Failed || $this === self::StockoutOverdue;
    }
}
