<?php

namespace App\Enums;

/**
 * 爬蟲的執行結果。
 *
 * 值直接當指令的 exit code 用：0 是慣例上的成功，非 0 讓排程知道要記錄並通知。
 * 分成 1 與 2 是為了在通知裡看得出「整支掛掉」與「抓到一部分」的差別——
 * 前者通常是被擋、要立刻處理。
 *
 * 後者（部分成功）不等於「沒做缺貨判定」，情況分好幾種，詳見
 * HmallProductService::fetchAllHmallProducts() 開頭的說明：目錄有缺頁、
 * 續跑、失敗資料缺商品編號，這三種確實沒做缺貨判定；但整份目錄看過一遍、
 * 只是排除了幾件寫入失敗的商品時，缺貨判定其實有做，只是排除清單不是空的。
 * 通知要看的是回傳結果附的那句說明文字，不能只看這個 enum 的分類。
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
