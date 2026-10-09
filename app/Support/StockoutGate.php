<?php

namespace App\Support;

use App\Enums\CrawlOutcome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 缺貨判定的最後兩道關卡，台灣與日本兩支爬蟲共用。
 *
 * 一是「看到的太少」：官網第 1 頁就回一個很小、但前後一致的總數時，CatalogScan
 * 看不出缺口，照做會把整個品牌標成缺貨。二是「太久沒做」：缺貨判定被跳過時
 * 只是一般的結束通知，天天跳過的話下架商品永遠不會下架，卻沒人發現。
 */
final class StockoutGate
{
    /**
     * 這一輪看到的件數低於目前在售件數的這個比例，就不做缺貨判定。
     * 正常一天下架的比例遠低於此；只擋「一次標掉半個品牌」這種一定是看漏的情況。
     */
    public const MIN_SEEN_RATIO = 0.5;

    /** 連續跳過超過這麼多天，改發失敗通知 */
    public const OVERDUE_AFTER_DAYS = 2;

    private const CACHE_KEY_LAST_STOCKOUT = 'stockout:last-run:%s:%s'; // source, brand

    public function __construct(
        private readonly string $source,
        private readonly string $brand,
    ) {}

    public function seenTooFew(int $itemsSeen, int $inStockCount): bool
    {
        return $itemsSeen < $inStockCount * self::MIN_SEEN_RATIO;
    }

    /**
     * 官網真的一次下架超過一半（例如換季）時這道會天天擋，維運者確認後要有不改程式的出口。
     */
    public static function seenTooFewNote(int $itemsSeen, int $inStockCount): string
    {
        return "未執行缺貨判定，這一輪只看到 {$itemsSeen} 件，目前在售 {$inStockCount} 件"
            .'（確認官網真的大量下架後，可加 --accept-shrink 重跑放行）';
    }

    /** 缺貨判定做完後呼叫 */
    public function recordRun(): void
    {
        Cache::forever($this->cacheKey(), today()->toDateString());
    }

    /**
     * 這一輪不做缺貨判定。平常回部分成功；連續太多天沒做就升級成要人處理的結果。
     */
    public function skip(string $reason): CrawlResult
    {
        $lastRun = Cache::get($this->cacheKey());

        // 還沒有紀錄（剛上線或快取被清）時，從今天開始算
        if ($lastRun === null) {
            $this->recordRun();

            return new CrawlResult(CrawlOutcome::PartiallySucceeded, $reason);
        }

        $daysSince = (int) Carbon::parse($lastRun)->diffInDays(today());

        if ($daysSince <= self::OVERDUE_AFTER_DAYS) {
            return new CrawlResult(CrawlOutcome::PartiallySucceeded, $reason);
        }

        logger()->error('Stockout has been skipped for too long', [
            'source' => $this->source,
            'brand' => $this->brand,
            'last_stockout_run' => $lastRun,
            'days_since' => $daysSince,
        ]);

        return new CrawlResult(
            CrawlOutcome::StockoutOverdue,
            "{$reason}；已經 {$daysSince} 天沒有執行缺貨判定（上次 {$lastRun}）"
        );
    }

    private function cacheKey(): string
    {
        return sprintf(self::CACHE_KEY_LAST_STOCKOUT, $this->source, $this->brand);
    }
}
