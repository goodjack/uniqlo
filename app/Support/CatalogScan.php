<?php

namespace App\Support;

/**
 * 一輪爬蟲有沒有把整份目錄看完，缺貨判定只能在看完時做。官網軟擋時常回 200
 * 加空清單、或把總數改小讓翻頁提早結束，所以要拿件數對總數；抓取途中總數縮水
 * 不到一批的量屬正常下架，不算缺口。位置用第幾件（從 0 起算）。
 */
final class CatalogScan
{
    private int $largestTotal = 0;

    private int $lastTotal = 0;

    private int $itemsSeen = 0;

    private ?int $firstGap = null;

    // 第一批件數不到「這一輪看過的最大總數」該有的量，總數縮水時缺口從這裡算起
    private ?int $firstShortfall = null;

    public function __construct(private readonly int $batchSize) {}

    public function expectedCount(int $offset, int $total): int
    {
        return min($this->batchSize, max(0, $total - $offset));
    }

    /** 件數不足就記成缺口並回傳 false */
    public function recordBatch(int $offset, int $count, int $total): bool
    {
        $this->largestTotal = max($this->largestTotal, $total);
        $this->lastTotal = $total;
        $this->itemsSeen += $count;

        if ($count < $this->expectedCount($offset, $this->largestTotal)) {
            $this->firstShortfall ??= $offset;
        }

        if ($count >= $this->expectedCount($offset, $total)) {
            return true;
        }

        $this->recordGap($offset);

        return false;
    }

    public function recordGap(int $offset): void
    {
        $this->firstGap ??= $offset;
    }

    /** 總數中途被大幅改小會讓翻頁提早結束，停下的位置之後都沒看到 */
    public function recordEnd(int $nextOffset): void
    {
        if ($nextOffset >= $this->largestTotal) {
            return;
        }

        if ($this->largestTotal - $this->lastTotal < $this->batchSize && $nextOffset >= $this->lastTotal) {
            return;
        }

        $this->recordGap($this->firstShortfall ?? $nextOffset);
    }

    public function firstGap(): ?int
    {
        return $this->firstGap;
    }

    public function hasGap(): bool
    {
        return $this->firstGap !== null;
    }

    public function itemsSeen(): int
    {
        return $this->itemsSeen;
    }

    public function largestTotal(): int
    {
        return $this->largestTotal;
    }

    public function pageOf(int $offset): int
    {
        return intdiv($offset, $this->batchSize) + 1;
    }
}
