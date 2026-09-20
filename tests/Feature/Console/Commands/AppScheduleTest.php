<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AppSchedule;
use App\Enums\CrawlOutcome;
use App\Events\AppTaskFailed;
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
        Event::fake([AppTaskFailed::class]);
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
     * 最常見的失敗形態不丟例外，只是安靜回傳 exit code 1
     * （FetchHmallProducts::handle() 就是 return $succeeded ? 0 : 1）。
     * 只包 try/catch 會整個漏掉這條路徑。
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
     * 爬蟲「抓到一部分、有幾頁失敗」時回傳 exit code 2，通知要標成部分成功，
     * 跟整支掛掉的完全失敗分開。
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
            $failedSteps = $event->data['failed_steps'];

            return in_array('hmall-product:fetch UNIQLO（部分成功）', $failedSteps, true)
                && in_array('sitemap:generate（完全失敗）', $failedSteps, true);
        });
    }

    /**
     * 兩種「部分成功」在通知裡要分得出來。
     *
     * 目錄有缺頁代表這一輪根本沒做缺貨判定、站上還是上一次完整掃描的結果；排除
     * 幾件寫入失敗的商品之後做了缺貨判定是另一回事，急迫程度不一樣。exit code
     * 只有一種「部分成功」，所以指令會另外留一句說明給排程接在後面。
     */
    public function test_the_notification_tells_the_two_kinds_of_partial_success_apart(): void
    {
        $this->fakeAllSteps(
            partiallySucceeding: ['hmall-product:fetch'],
            notes: ['hmall-product:fetch UNIQLO' => '已執行缺貨判定，排除 1 件寫入失敗商品'],
        );

        $this->artisan('app:schedule')->run();

        Event::assertDispatched(AppTaskFailed::class, function (AppTaskFailed $event) {
            $failedSteps = $event->data['failed_steps'];

            return in_array(
                'hmall-product:fetch UNIQLO（部分成功：已執行缺貨判定，排除 1 件寫入失敗商品）',
                $failedSteps,
                true
            )
                // 沒留說明的步驟維持原本的寫法，說明也不會跑到別的品牌那一行去
                && in_array('hmall-product:fetch GU（部分成功）', $failedSteps, true);
        });
    }

    /**
     * 把排程的每個步驟換成假指令，避免測試真的去打官網或寫資料庫。
     *
     * @param  array<int, string>  $failing  這些指令回傳 exit code 1
     * @param  array<int, string>  $throwing  這些指令丟例外
     * @param  array<int, string>  $partiallySucceeding  這些指令回傳爬蟲的「部分成功」
     * @param  array<string, string>  $notes  指令留給通知的說明，key 是「指令名 品牌」
     */
    private function fakeAllSteps(
        array $failing = [],
        array $throwing = [],
        array $partiallySucceeding = [],
        array $notes = [],
    ): void {
        $test = $this;

        foreach ($this->stepCommands() as $command) {
            $fake = new ClosureCommand(
                "{$command} {brand?} {country?} {--only-recent} {--is-scheduled}",
                function () use ($test, $command, $failing, $throwing, $partiallySucceeding, $notes) {
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
