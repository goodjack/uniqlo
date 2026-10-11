<?php

namespace App\Support;

use App\Enums\CrawlOutcome;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 缺貨判定的最後兩道關卡。看到的太少：官網第 1 頁就回一個很小但前後一致的總數時，
 * CatalogScan 看不出缺口。太久沒做：天天跳過的話下架商品永遠不會下架，卻沒人發現。
 */
final class StockoutGate
{
    /** 正常一天下架的比例遠低於此，只擋「一次標掉半個品牌」這種一定是看漏的情況 */
    public const MIN_SEEN_RATIO = 0.5;

    public const OVERDUE_AFTER_DAYS = 2;

    private const CACHE_KEY_LAST_STOCKOUT = 'stockout:last-run:%s:%s'; // source, brand

    public function __construct(
        private readonly string $source,
        private readonly string $brand,
    ) {}

    /**
     * 目錄看完、沒有缺口之後決定做不做缺貨判定。$markStockout 收排除名單、真的去標缺貨；
     * 只有前面幾關都過了才會去算在售件數。
     *
     * @param  array<int, string>  $failedIds
     */
    public function decide(
        CatalogScan $scan,
        bool $resumed,
        array $failedIds,
        int $unidentifiedFailures,
        Closure $countInStock,
        bool $acceptShrink,
        Closure $markStockout,
    ): CrawlResult {
        $context = ['source' => $this->source, 'brand' => $this->brand];

        // 續跑只看了目錄的後半段，前半段這一輪沒被摸到
        if ($resumed) {
            logger()->info('Resumed crawl finished cleanly - stockout deferred to the next full scan', $context);

            return $this->skip('未執行缺貨判定，這一輪是從上次中斷的地方接著跑');
        }

        if ($scan->itemsSeen() === 0) {
            logger()->error('The catalog came back empty - skipping stockout', $context);

            return $this->skip('未執行缺貨判定，這一輪一件商品都沒看到');
        }

        $failedIds = array_values(array_unique($failedIds));

        if ($unidentifiedFailures > 0) {
            logger()->error('Save failures without an identifier - skipping stockout', $context + [
                'unidentified_failures' => $unidentifiedFailures,
                'failed_ids' => $failedIds,
            ]);

            return $this->skip("未執行缺貨判定，{$unidentifiedFailures} 件失敗資料缺少{$this->identifierLabel()}");
        }

        $inStockCount = $countInStock();

        if (! $acceptShrink && $this->seenTooFew($scan->itemsSeen(), $inStockCount)) {
            logger()->error('Saw far fewer products than are in stock - skipping stockout', $context + [
                'items_seen' => $scan->itemsSeen(),
                'in_stock' => $inStockCount,
            ]);

            return $this->skip(self::seenTooFewNote($scan->itemsSeen(), $inStockCount));
        }

        $markStockout($failedIds);
        $this->recordRun();

        if ($failedIds === []) {
            return new CrawlResult(CrawlOutcome::Succeeded);
        }

        logger()->error('Stockout ran with the products that failed to save excluded', $context + [
            'excluded_ids' => $failedIds,
        ]);

        return new CrawlResult(
            CrawlOutcome::PartiallySucceeded,
            sprintf('已執行缺貨判定，排除 %d 件寫入失敗商品', count($failedIds))
        );
    }

    public function seenTooFew(int $itemsSeen, int $inStockCount): bool
    {
        return $itemsSeen < $inStockCount * self::MIN_SEEN_RATIO;
    }

    public static function seenTooFewNote(int $itemsSeen, int $inStockCount): string
    {
        return "未執行缺貨判定，這一輪只看到 {$itemsSeen} 件，目前在售 {$inStockCount} 件"
            .'（確認官網真的大量下架後，可加 --accept-shrink 重跑放行）';
    }

    public function recordRun(): void
    {
        Cache::forever($this->cacheKey(), today()->toDateString());
    }

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

    private function identifierLabel(): string
    {
        return $this->source === 'japan' ? '商品識別' : '商品編號';
    }

    private function cacheKey(): string
    {
        return sprintf(self::CACHE_KEY_LAST_STOCKOUT, $this->source, $this->brand);
    }
}
