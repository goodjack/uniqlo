<?php

namespace Tests\Unit\Support;

use App\Enums\CrawlOutcome;
use App\Support\StockoutGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockoutGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Cache::flush();
        Carbon::setTestNow('2026-10-09 09:30:00');
    }

    /**
     * 跳過兩天以內只是部分成功；超過兩天改成要人處理，說明裡寫出幾天、上次哪天。
     */
    #[DataProvider('daysSinceLastRun')]
    public function test_skipping_escalates_only_after_two_days(int $daysAgo, CrawlOutcome $expected): void
    {
        Cache::forever('stockout:last-run:hmall:UNIQLO', today()->subDays($daysAgo)->toDateString());

        $result = (new StockoutGate('hmall', 'UNIQLO'))->skip('未執行缺貨判定，目錄有缺頁（最早在第 2 頁）');

        $this->assertSame($expected, $result->outcome);
    }

    public static function daysSinceLastRun(): array
    {
        return [
            'yesterday' => [1, CrawlOutcome::PartiallySucceeded],
            'two days ago' => [2, CrawlOutcome::PartiallySucceeded],
            'three days ago' => [3, CrawlOutcome::StockoutOverdue],
        ];
    }

    public function test_the_overdue_note_says_how_long_and_since_when(): void
    {
        Cache::forever('stockout:last-run:japan:GU', '2026-10-05');

        $result = (new StockoutGate('japan', 'GU'))->skip('未執行缺貨判定，目錄有缺頁（最早在第 2 頁）');

        $this->assertSame(
            '未執行缺貨判定，目錄有缺頁（最早在第 2 頁）；已經 4 天沒有執行缺貨判定（上次 2026-10-05）',
            $result->note
        );
    }

    /**
     * 剛上線還沒有紀錄時從今天起算，不會一上線就發失敗通知，也不會永遠不升級。
     */
    public function test_the_first_skip_without_a_record_starts_counting_from_today(): void
    {
        $gate = new StockoutGate('hmall', 'GU');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $gate->skip('未執行缺貨判定')->outcome);
        $this->assertSame('2026-10-09', Cache::get('stockout:last-run:hmall:GU'));

        Carbon::setTestNow('2026-10-12 09:30:00');

        $this->assertSame(CrawlOutcome::StockoutOverdue, $gate->skip('未執行缺貨判定')->outcome);
    }

    public function test_each_source_and_brand_counts_on_its_own(): void
    {
        Cache::forever('stockout:last-run:hmall:UNIQLO', '2026-10-01');
        (new StockoutGate('japan', 'UNIQLO'))->recordRun();

        $this->assertSame(
            CrawlOutcome::PartiallySucceeded,
            (new StockoutGate('japan', 'UNIQLO'))->skip('未執行缺貨判定')->outcome
        );
        $this->assertSame(
            CrawlOutcome::StockoutOverdue,
            (new StockoutGate('hmall', 'UNIQLO'))->skip('未執行缺貨判定')->outcome
        );
    }

    /**
     * 看到的件數不到在售件數的一半就不做缺貨判定；剛建好的空資料庫（在售 0 件）照做。
     */
    #[DataProvider('seenCounts')]
    public function test_seeing_far_fewer_than_in_stock_blocks_stockout(int $seen, int $inStock, bool $tooFew): void
    {
        $this->assertSame($tooFew, (new StockoutGate('hmall', 'UNIQLO'))->seenTooFew($seen, $inStock));
    }

    public static function seenCounts(): array
    {
        return [
            'a small but self-consistent first page' => [24, 1672, true],
            'just under half' => [835, 1672, true],
            'half' => [836, 1672, false],
            'a normal day' => [1660, 1672, false],
            'an empty database' => [24, 0, false],
        ];
    }
}
