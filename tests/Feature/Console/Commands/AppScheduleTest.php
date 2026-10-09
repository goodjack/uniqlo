<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AppSchedule;
use App\Enums\CrawlOutcome;
use App\Events\AppTaskFailed;
use App\Events\AppTaskFinished;
use App\Support\TaskNotes;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Event;
use ReflectionMethod;
use Tests\TestCase;

class AppScheduleTest extends TestCase
{
    /** @var array<int, string> 實際被執行到的步驟，用來驗證失敗後有沒有繼續往下跑 */
    private array $executedCommands = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->executedCommands = [];
        Event::fake([AppTaskFailed::class, AppTaskFinished::class]);
    }

    public function test_all_steps_run_and_command_succeeds_when_nothing_fails(): void
    {
        $this->fakeAllSteps();

        $exitCode = $this->artisan('app:schedule')->run();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame($this->stepCommands(), $this->executedCommands);
        Event::assertNotDispatched(AppTaskFailed::class);
    }

    public function test_later_steps_still_run_after_a_step_throws(): void
    {
        $this->fakeAllSteps(throwing: ['hmall-product:fetch']);

        $exitCode = $this->artisan('app:schedule')->run();

        $this->assertSame(Command::FAILURE, $exitCode);
        // 爬蟲炸掉之後，快取重建與 sitemap 這些後段步驟仍然要跑到
        $this->assertContains('hmall-product:cache', $this->executedCommands);
        $this->assertContains('sitemap:generate', $this->executedCommands);
        $this->assertSame($this->stepCommands(), $this->executedCommands);
    }

    /**
     * 爬蟲失敗時不丟例外，只回傳非 0 的 exit code，只包 try/catch 會漏掉。
     */
    public function test_non_zero_exit_code_counts_as_failure(): void
    {
        $this->fakeAllSteps(failing: ['hmall-product:fetch']);

        $exitCode = $this->artisan('app:schedule')->run();

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function test_failure_notification_lists_every_failed_step(): void
    {
        $this->fakeAllSteps(failing: ['hmall-product:fetch'], throwing: ['sitemap:generate']);

        $this->artisan('app:schedule')->run();

        Event::assertDispatched(AppTaskFailed::class, function (AppTaskFailed $event) {
            $failedSteps = $event->data['failed_steps'];

            // hmall-product:fetch 有 UNIQLO 與 GU 兩個步驟，加上 sitemap:generate 共三個
            return count($failedSteps) === 3
                && in_array('hmall-product:fetch UNIQLO（完全失敗）', $failedSteps, true)
                && in_array('hmall-product:fetch GU（完全失敗）', $failedSteps, true)
                && in_array('sitemap:generate（丟出例外）', $failedSteps, true);
        });
    }

    /**
     * 爬蟲「抓到一部分、有幾頁失敗」時回傳 exit code 2，要跟整支掛掉的完全失敗
     * 分開計數，不可以塞進同一句「N of M scheduled steps failed」。
     */
    public function test_partial_crawl_success_is_reported_separately_from_total_failure(): void
    {
        $this->fakeAllSteps(
            failing: ['sitemap:generate'],
            partiallySucceeding: ['hmall-product:fetch'],
        );

        $exitCode = $this->artisan('app:schedule')->run();

        $this->assertSame(Command::FAILURE, $exitCode);

        Event::assertDispatched(AppTaskFailed::class, function (AppTaskFailed $event) {
            return $event->data['failed_steps'] === ['sitemap:generate（完全失敗）']
                && in_array(
                    'hmall-product:fetch UNIQLO（部分成功）',
                    $event->data['partial_steps'],
                    true
                );
        });
    }

    /**
     * 只有部分成功時不送紅色的失敗通知：部分成功可能天天發生，每天一封紅字會讓人
     * 學會忽略，真正的整步失敗就被淹掉。
     */
    public function test_a_day_with_only_partial_successes_does_not_send_a_failure_notification(): void
    {
        $this->fakeAllSteps(partiallySucceeding: ['hmall-product:fetch']);

        $exitCode = $this->artisan('app:schedule')->run();

        $this->assertSame(Command::SUCCESS, $exitCode);
        Event::assertNotDispatched(AppTaskFailed::class);

        Event::assertDispatched(AppTaskFinished::class, function (AppTaskFinished $event) {
            return $event->data['partial_steps'] === [
                'hmall-product:fetch UNIQLO（部分成功）',
                'hmall-product:fetch GU（部分成功）',
            ]
                && ! array_key_exists('failed_steps', $event->data);
        });
    }

    /**
     * 日本官網那支也會回部分成功（沒做缺貨判定），不能被當成整步失敗。
     */
    public function test_the_japan_crawler_can_also_partially_succeed(): void
    {
        $this->fakeAllSteps(partiallySucceeding: ['japan-product:fetch']);

        $this->assertSame(Command::SUCCESS, $this->artisan('app:schedule')->run());
        Event::assertNotDispatched(AppTaskFailed::class);
        Event::assertDispatched(AppTaskFinished::class, fn (AppTaskFinished $event) => $event->data['partial_steps'] === [
            'japan-product:fetch UNIQLO（部分成功）',
            'japan-product:fetch GU（部分成功）',
        ]);
    }

    /**
     * 兩種部分成功在通知裡要分得出來（有沒有做缺貨判定），靠指令留的說明。
     */
    public function test_the_notification_tells_the_two_kinds_of_partial_success_apart(): void
    {
        $this->fakeAllSteps(
            partiallySucceeding: ['hmall-product:fetch'],
            notes: ['hmall-product:fetch UNIQLO' => '已執行缺貨判定，排除 1 件寫入失敗商品'],
        );

        $this->artisan('app:schedule')->run();

        Event::assertDispatched(AppTaskFinished::class, function (AppTaskFinished $event) {
            $partialSteps = $event->data['partial_steps'];

            return in_array(
                'hmall-product:fetch UNIQLO（部分成功：已執行缺貨判定，排除 1 件寫入失敗商品）',
                $partialSteps,
                true
            )
                // 沒留說明的步驟維持原本的寫法，說明也不會跑到別的品牌那一行去
                && in_array('hmall-product:fetch GU（部分成功）', $partialSteps, true);
        });
    }

    /**
     * 缺貨判定連續多天沒做時，就算資料有更新也要發紅色的失敗通知，不能混在一般的結束通知裡。
     */
    public function test_an_overdue_stockout_sends_a_failure_notification(): void
    {
        $this->fakeAllSteps(
            overdue: ['japan-product:fetch'],
            notes: ['japan-product:fetch GU' => '未執行缺貨判定，目錄有缺頁（最早在第 2 頁）；已經 3 天沒有執行缺貨判定（上次 2026-10-06）'],
        );

        $this->assertSame(Command::FAILURE, $this->artisan('app:schedule')->run());

        Event::assertDispatched(AppTaskFailed::class, fn (AppTaskFailed $event) => in_array(
            'japan-product:fetch GU（缺貨判定逾期：未執行缺貨判定，目錄有缺頁（最早在第 2 頁）；已經 3 天沒有執行缺貨判定（上次 2026-10-06））',
            $event->data['failed_steps'],
            true
        ));
    }

    /**
     * 非爬蟲步驟的 exit code 2 是 Symfony 的 Command::INVALID（整步沒做），
     * 不可以讀成「部分成功」。
     */
    public function test_a_non_crawler_step_returning_two_is_not_called_a_partial_success(): void
    {
        $this->fakeAllSteps(partiallySucceeding: ['sitemap:generate']);

        $this->artisan('app:schedule')->run();

        Event::assertDispatched(AppTaskFailed::class, function (AppTaskFailed $event) {
            return in_array('sitemap:generate（exit code 2）', $event->data['failed_steps'], true);
        });
    }

    /**
     * 把排程的每個步驟換成假指令，避免測試真的去打官網或寫資料庫。
     *
     * @param  array<int, string>  $failing  這些指令回傳 exit code 1
     * @param  array<int, string>  $throwing  這些指令丟例外
     * @param  array<int, string>  $partiallySucceeding  這些指令回傳爬蟲的「部分成功」
     * @param  array<int, string>  $overdue  這些指令回傳爬蟲的「缺貨判定逾期」
     * @param  array<string, string>  $notes  指令留給通知的說明，key 是「指令名 品牌」
     */
    private function fakeAllSteps(
        array $failing = [],
        array $throwing = [],
        array $partiallySucceeding = [],
        array $notes = [],
        array $overdue = [],
    ): void {
        $test = $this;

        foreach ($this->stepCommands() as $command) {
            $fake = new ClosureCommand(
                "{$command} {brand?} {country?} {--only-recent} {--is-scheduled}",
                function () use ($test, $command, $failing, $throwing, $partiallySucceeding, $notes, $overdue) {
                    $test->recordExecution($command);

                    // 真的指令跑完會把這一輪的說明留給排程，假指令照做
                    $brand = $this->argument('brand');
                    $note = $notes[trim("{$command} {$brand}")] ?? null;

                    if ($note !== null) {
                        app(TaskNotes::class)->put($command, $brand, $note);
                    }

                    if (in_array($command, $throwing, true)) {
                        throw new Exception("測試用的失敗：{$command}");
                    }

                    if (in_array($command, $partiallySucceeding, true)) {
                        return CrawlOutcome::PartiallySucceeded->value;
                    }

                    if (in_array($command, $overdue, true)) {
                        return CrawlOutcome::StockoutOverdue->value;
                    }

                    return in_array($command, $failing, true) ? 1 : 0;
                }
            );

            // 直接掛到已經啟動的 Artisan application，才蓋得掉同名的真指令
            $this->app[Kernel::class]->registerCommand($fake);
        }
    }

    public function recordExecution(string $command): void
    {
        $this->executedCommands[] = $command;
    }

    /**
     * 依 AppSchedule::steps() 的順序取出指令名，排程增減步驟時測試自動跟上。
     *
     * @return array<int, string>
     */
    private function stepCommands(): array
    {
        $steps = (new ReflectionMethod(AppSchedule::class, 'steps'))->invoke(null);

        return array_map(fn (array $step) => $step[0], $steps);
    }
}
