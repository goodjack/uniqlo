<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AppSchedule;
use App\Enums\CrawlOutcome;
use App\Events\AppTaskFailed;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Event;
use ReflectionClass;
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
                && in_array('hmall-product:fetch UNIQLO（exit code 1）', $failedSteps, true)
                && in_array('hmall-product:fetch GU（exit code 1）', $failedSteps, true)
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
                && in_array('sitemap:generate（exit code 1）', $failedSteps, true);
        });
    }

    /**
     * 把排程的每個步驟換成假指令，避免測試真的去打官網或寫資料庫。
     *
     * @param  array<int, string>  $failing  這些指令回傳 exit code 1
     * @param  array<int, string>  $throwing  這些指令丟例外
     * @param  array<int, string>  $partiallySucceeding  這些指令回傳爬蟲的「部分成功」
     */
    private function fakeAllSteps(
        array $failing = [],
        array $throwing = [],
        array $partiallySucceeding = [],
    ): void {
        $test = $this;

        foreach ($this->stepCommands() as $command) {
            $fake = new ClosureCommand(
                "{$command} {brand?} {country?} {--only-recent} {--is-scheduled}",
                function () use ($test, $command, $failing, $throwing, $partiallySucceeding) {
                    $test->recordExecution($command);

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
     * 依 AppSchedule::STEPS 的順序取出指令名，排程增減步驟時測試自動跟上。
     *
     * @return array<int, string>
     */
    private function stepCommands(): array
    {
        $steps = (new ReflectionClass(AppSchedule::class))
            ->getReflectionConstant('STEPS')
            ->getValue();

        return array_map(fn (array $step) => $step[0], $steps);
    }
}
