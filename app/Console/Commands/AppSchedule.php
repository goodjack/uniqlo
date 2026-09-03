<?php

namespace App\Console\Commands;

use App\Enums\CrawlOutcome;
use App\Events\AppTaskFailed;
use Illuminate\Console\Command;
use Throwable;

class AppSchedule extends Command
{
    /**
     * 每日排程的步驟，順序即執行順序。
     *
     * 前面的爬蟲失敗不會擋住後面的快取重建與 sitemap：資料庫裡還有昨天的完整資料，
     * 讓快取跟著資料庫走，比讓整條排程斷在第一步安全。
     */
    private const STEPS = [
        ['hmall-product:fetch', ['brand' => 'UNIQLO']],
        ['hmall-product:fetch', ['brand' => 'GU']],
        ['hmall-product-description:fetch', ['brand' => 'UNIQLO']],
        ['hmall-product-description:fetch', ['brand' => 'GU']],
        ['japan-product:fetch', ['brand' => 'UNIQLO']],
        ['japan-product:fetch', ['brand' => 'GU']],
        ['hmall-product:cache'],
        ['hmall-product:cache-most-visited'],
        ['sitemap:generate'],
        ['style:fetch', ['brand' => 'UNIQLO']],
        ['style:fetch', ['brand' => 'GU']],
        ['style-hint:fetch', ['country' => 'us']],
        ['style-hint:fetch', ['country' => 'jp']],
        ['style-hint-ugc:fetch', ['brand' => 'UNIQLO', '--only-recent' => true, '--is-scheduled' => true]],
        ['style-hint-ugc:fetch', ['brand' => 'GU', '--only-recent' => true, '--is-scheduled' => true]],
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the daily schedule';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $failedSteps = [];

        foreach (self::STEPS as $step) {
            $command = $step[0];
            $arguments = $step[1] ?? [];

            $failure = $this->runStep($command, $arguments);

            if ($failure !== null) {
                $failedSteps[] = $failure;
            }
        }

        if (empty($failedSteps)) {
            return self::SUCCESS;
        }

        $this->reportFailures($failedSteps);

        return self::FAILURE;
    }

    /**
     * 執行單一步驟，成功回傳 null，失敗回傳給通知看的描述。
     *
     * 失敗有兩種形態，兩種都要攔：丟例外，以及安靜回傳非 0 的 exit code
     * （例如 FetchHmallProducts 爬蟲失敗時就是回傳非 0 而不丟例外）。
     */
    private function runStep(string $command, array $arguments): ?string
    {
        try {
            $exitCode = $this->call($command, $arguments);
        } catch (Throwable $e) {
            report($e);
            logger()->error('Scheduled step threw an exception', [
                'command' => $command,
                'arguments' => $arguments,
            ]);

            return $this->describeStep($command, $arguments, '丟出例外');
        }

        if ($exitCode !== self::SUCCESS) {
            logger()->error('Scheduled step returned a failure exit code', [
                'command' => $command,
                'arguments' => $arguments,
                'exit_code' => $exitCode,
            ]);

            return $this->describeStep($command, $arguments, $this->describeExitCode($exitCode));
        }

        return null;
    }

    /**
     * 把 exit code 翻成通知裡看得懂的原因。
     *
     * 「部分成功」與「完全失敗」要分開，因為要看的東西不同：部分成功代表資料庫裡
     * 還有昨天的完整資料、而且這一輪刻意沒做缺貨判定，站上顯示的是舊資料；完全
     * 失敗代表連一頁都沒抓到，通常是被擋。兩種都不會自己好，差別只在急迫程度。
     *
     * CrawlOutcome 認得的值一律用它的中文標籤。非爬蟲步驟的 exit code 1 也會被
     * 標成「完全失敗」——對那些步驟來說 1 本來就是整步失敗，讀起來仍然對。
     */
    private function describeExitCode(int $exitCode): string
    {
        $outcome = CrawlOutcome::tryFrom($exitCode);

        if ($outcome !== null && $outcome !== CrawlOutcome::Succeeded) {
            return $outcome->label();
        }

        return "exit code {$exitCode}";
    }

    private function describeStep(string $command, array $arguments, string $reason): string
    {
        $describedArguments = collect($arguments)
            ->reject(fn ($value, $key) => str_starts_with((string) $key, '--'))
            ->implode(' ');

        return trim("{$command} {$describedArguments}")."（{$reason}）";
    }

    /**
     * @param  array<int, string>  $failedSteps
     */
    private function reportFailures(array $failedSteps): void
    {
        $this->error(sprintf('%d of %d scheduled steps failed', count($failedSteps), count(self::STEPS)));

        logger()->error('Daily schedule finished with failures', [
            'failed_steps' => $failedSteps,
            'total_steps' => count(self::STEPS),
        ]);

        AppTaskFailed::dispatch(class_basename(__CLASS__), null, null, [
            'failed_steps' => $failedSteps,
            'total_steps' => count(self::STEPS),
        ]);
    }
}
