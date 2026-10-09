<?php

namespace App\Support;

/**
 * 一輪爬蟲有沒有把整份目錄看完。缺貨判定只能在看完時做，看漏的那一段會被整批
 * 誤判成下架。官網被軟擋時常回 200 加空清單、或把總數改小讓翻頁提早結束，所以
 * 「請求成功」不等於「那一段看到了」，要拿件數對總數。
 *
 * 抓取途中有商品下架、總數小幅縮水很正常：只會讓最後一批變短或翻頁少一頁，
 * 縮水不到一批的量都不算缺口。
 *
 * 位置一律用第幾件（從 0 起算），分頁與 offset 兩種翻法共用。
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

    /**
     * 一批照它自己回的總數該有幾件。呼叫端拿來判斷要不要重打這一批。
     */
    public function expectedCount(int $offset, int $total): int
    {
        return min($this->batchSize, max(0, $total - $offset));
    }

    /**
     * 記下一批成功的回應。件數少於這一批該有的量就記成缺口，回傳 false。
     */
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

    /**
     * 翻頁停下時呼叫。總數中途被大幅改小會讓翻頁提早結束，停下的位置之後都沒看到。
     */
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

    /** 第幾件落在第幾頁（從 1 起算），給續跑點與通知用 */
    public function pageOf(int $offset): int
    {
        return intdiv($offset, $this->batchSize) + 1;
    }
}
