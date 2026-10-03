<?php

namespace App\Console\Commands;

use App\Enums\Brand;
use App\Enums\CrawlOutcome;
use App\Events\AppTaskFailed;
use App\Events\AppTaskFinished;
use App\Support\TaskNotes;
use Illuminate\Console\Command;
use Throwable;

class AppSchedule extends Command
{
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
        $steps = self::steps();
        $failedSteps = [];
        $partialSteps = [];

        foreach ($steps as $step) {
            $command = $step[0];
            $arguments = $step[1] ?? [];

            $outcome = $this->runStep($command, $arguments);

            if ($outcome === null) {
                continue;
            }

            [$description, $isPartial] = $outcome;

            if ($isPartial) {
                $partialSteps[] = $description;
            } else {
                $failedSteps[] = $description;
            }
        }

        if ($failedSteps === [] && $partialSteps === []) {
            return self::SUCCESS;
        }

        $this->reportOutcome($failedSteps, $partialSteps, count($steps));

        return $failedSteps === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * exit code 照 CrawlOutcome 解讀的指令。其他指令的 2 是 Symfony 的
     * Command::INVALID（參數不合法），不能讀成「部分成功」。
     */
    private const PARTIAL_SUCCESS_COMMANDS = [
        'hmall-product:fetch',
    ];

    /**
     * 每日排程的步驟，順序即執行順序。一步失敗照樣往下跑：資料庫裡還有昨天的
     * 完整資料，快取與 sitemap 跟著資料庫重建，比整條斷在第一步安全。
     *
     * 寫成方法而不是 const：const 運算式要 PHP 8.3 才能取 enum 的 ->value。
     *
     * @return array<int, array{0: string, 1?: array<string, mixed>}>
     */
    private static function steps(): array
    {
        return [
            ['hmall-product:fetch', ['brand' => Brand::Uniqlo->value]],
            ['hmall-product:fetch', ['brand' => Brand::Gu->value]],
            ['hmall-product-description:fetch', ['brand' => Brand::Uniqlo->value]],
            ['hmall-product-description:fetch', ['brand' => Brand::Gu->value]],
            ['japan-product:fetch', ['brand' => Brand::Uniqlo->value]],
            ['japan-product:fetch', ['brand' => Brand::Gu->value]],
            ['hmall-product:cache'],
            ['hmall-product:cache-most-visited'],
            ['sitemap:generate'],
            ['style:fetch', ['brand' => Brand::Uniqlo->value]],
            ['style:fetch', ['brand' => Brand::Gu->value]],
            ['style-hint:fetch', ['country' => 'us']],
            ['style-hint:fetch', ['country' => 'jp']],
            ['style-hint-ugc:fetch', ['brand' => Brand::Uniqlo->value, '--only-recent' => true, '--is-scheduled' => true]],
            ['style-hint-ugc:fetch', ['brand' => Brand::Gu->value, '--only-recent' => true, '--is-scheduled' => true]],
        ];
    }

    /**
     * 執行單一步驟。完全成功回傳 null，否則回傳通知用的描述與「是不是只抓到一部分」。
     * 失敗可能是丟例外，也可能只回傳非 0 的 exit code，兩種都要攔。
     *
     * @return array{0: string, 1: bool}|null
     */
    private function runStep(string $command, array $arguments): ?array
    {
        try {
            $exitCode = $this->call($command, $arguments);
        } catch (Throwable $e) {
            report($e);
            logger()->error('Scheduled step threw an exception', [
                'command' => $command,
                'arguments' => $arguments,
            ]);

            return [$this->describeStep($command, $arguments, '丟出例外'), false];
        }

        if ($exitCode === self::SUCCESS) {
            return null;
        }

        $isPartial = $this->isPartialSuccess($command, $exitCode);

        logger()->error('Scheduled step did not finish cleanly', [
            'command' => $command,
            'arguments' => $arguments,
            'exit_code' => $exitCode,
            'partial' => $isPartial,
        ]);

        return [
            $this->describeStep($command, $arguments, $this->describeExitCode($command, $exitCode)),
            $isPartial,
        ];
    }

    /**
     * 把 exit code 翻成通知裡的原因。1 對每個步驟都是整步失敗；其他沒有共同約定
     * 的值照實寫出來，不猜意思。
     */
    private function describeExitCode(string $command, int $exitCode): string
    {
        if ($this->isPartialSuccess($command, $exitCode)) {
            return CrawlOutcome::PartiallySucceeded->label();
        }

        return $exitCode === self::FAILURE
            ? CrawlOutcome::Failed->label()
            : "exit code {$exitCode}";
    }

    private function isPartialSuccess(string $command, int $exitCode): bool
    {
        return in_array($command, self::PARTIAL_SUCCESS_COMMANDS, true)
            && CrawlOutcome::tryFrom($exitCode) === CrawlOutcome::PartiallySucceeded;
    }

    /**
     * 組出通知裡的一行，例如
     * 「hmall-product:fetch UNIQLO（部分成功：已執行缺貨判定，排除 1 件寫入失敗商品）」。
     * 指令有留說明（TaskNotes）就接在後面：同樣是部分成功，有沒有做缺貨判定急迫程度不同。
     */
    private function describeStep(string $command, array $arguments, string $reason): string
    {
        $describedArguments = collect($arguments)
            ->reject(fn ($value, $key) => str_starts_with((string) $key, '--'))
            ->implode(' ');

        $note = app(TaskNotes::class)->pull($command, $describedArguments);

        if ($note !== null) {
            $reason = "{$reason}：{$note}";
        }

        return trim("{$command} {$describedArguments}")."（{$reason}）";
    }

    /**
     * 發出彙整通知。只有部分成功時發一般的結束通知，有整步失敗才發紅色的失敗通知：
     * 部分成功可能天天發生，每天一封紅字會讓人學會忽略，真正的失敗反而被淹掉。
     *
     * @param  array<int, string>  $failedSteps
     * @param  array<int, string>  $partialSteps
     */
    private function reportOutcome(array $failedSteps, array $partialSteps, int $totalSteps): void
    {
        $data = [];

        if ($failedSteps !== []) {
            $data['failed_steps'] = $failedSteps;
        }

        if ($partialSteps !== []) {
            $data['partial_steps'] = $partialSteps;
        }

        $data['total_steps'] = $totalSteps;

        if ($failedSteps === []) {
            $this->info(sprintf(
                '%d of %d scheduled steps partially succeeded',
                count($partialSteps),
                $totalSteps
            ));

            logger()->info('Daily schedule finished with partial successes', $data);

            AppTaskFinished::dispatch(class_basename(__CLASS__), null, null, $data);

            return;
        }

        $this->error(sprintf('%d of %d scheduled steps failed', count($failedSteps), $totalSteps));

        logger()->error('Daily schedule finished with failures', $data);

        AppTaskFailed::dispatch(class_basename(__CLASS__), null, null, $data);
    }
}
