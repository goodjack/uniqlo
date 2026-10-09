<?php

namespace Tests\Unit\Support;

use App\Support\CatalogScan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CatalogScanTest extends TestCase
{
    /**
     * 每一批是 [件數, 那一批回的總數]，依序從第 0 件開始；最後照實際翻頁停下的位置收尾。
     *
     * @param  array<int, array{0: int, 1: int}>  $batches
     */
    #[DataProvider('completeScans')]
    public function test_a_complete_scan_has_no_gap(int $batchSize, array $batches): void
    {
        $this->assertFalse($this->scan($batchSize, $batches)->hasGap());
    }

    public static function completeScans(): array
    {
        return [
            // 途中下架一件、總數剛好跨回整頁，翻頁少一頁是正常的
            'the total drops by one across a page boundary' => [24, [...array_fill(0, 9, [24, 241]), [24, 240]]],
            'the total drops by one within the last page' => [24, [...array_fill(0, 9, [24, 240]), [23, 239]]],
            'the last page is exactly full' => [24, array_fill(0, 10, [24, 240])],
            'the total grows then shrinks back by less than a page' => [24, [[24, 240], [24, 260], ...array_fill(0, 8, [24, 240])]],
            'a japan sized batch crossing the boundary' => [36, [[36, 73], [36, 72]]],
        ];
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $batches
     */
    #[DataProvider('incompleteScans')]
    public function test_an_incomplete_scan_has_a_gap_at_the_first_missing_item(int $batchSize, array $batches, int $firstGap): void
    {
        $scan = $this->scan($batchSize, $batches);

        $this->assertTrue($scan->hasGap());
        $this->assertSame($firstGap, $scan->firstGap());
    }

    public static function incompleteScans(): array
    {
        return [
            'a later page comes back empty' => [24, [[24, 240], [0, 240]], 24],
            // 總數被大幅改小，翻頁提早結束：後面整段都沒看到
            'the total collapses partway' => [24, [[24, 1000], [24, 30]], 48],
            'the total shrinks by a full page' => [24, [[24, 72], [24, 48]], 48],
            'a short last page with an unchanged total' => [24, [[24, 30], [0, 30]], 24],
        ];
    }

    public function test_the_expected_count_follows_the_batch_own_total(): void
    {
        $scan = new CatalogScan(24);

        $this->assertSame(24, $scan->expectedCount(0, 30));
        $this->assertSame(6, $scan->expectedCount(24, 30));
        $this->assertSame(0, $scan->expectedCount(48, 30));
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $batches
     */
    private function scan(int $batchSize, array $batches): CatalogScan
    {
        $scan = new CatalogScan($batchSize);
        $offset = 0;

        foreach ($batches as [$count, $total]) {
            $scan->recordBatch($offset, $count, $total);
            $offset += $batchSize;
        }

        $scan->recordEnd($offset);

        return $scan;
    }
}
