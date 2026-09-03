<?php

namespace App\Enums;

/**
 * 爬蟲的執行結果。
 *
 * 值直接當指令的 exit code 用：0 是慣例上的成功，非 0 讓排程知道要記錄並通知。
 * 分成 1 與 2 是為了在通知裡看得出「整支掛掉」與「抓到一部分」的差別——
 * 前者通常是被擋、要立刻處理；後者代表這一輪刻意沒做缺貨判定（資料不完整），
 * 站上顯示的是上一次完整掃描的結果。兩種都不會自己好，差別只在急迫程度。
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
