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
     * exit code 要用 CrawlOutcome 翻譯的指令。
     *
     * CrawlOutcome::PartiallySucceeded 的值是 2，而 2 同時是 Symfony 留給
     * 「參數不合法」的 Command::INVALID。目前十五個步驟裡只有 hmall-product:fetch
     * 會回 2，所以今天沒有影響；但日後任何人照慣例在別的步驟寫 return self::INVALID，
     * 通知就會寫成「部分成功」，值班的人會以為只是抓到一部分、可以晚點看，實際上
     * 那一步整步沒做。
     *
     * 與其把 PartiallySucceeded 換成另一個「目前沒人用」的數字（下一個人照樣可能
     * 撞上），不如把翻譯限定在真的回傳 CrawlOutcome 的指令上。
     */
    private const PARTIAL_SUCCESS_COMMANDS = [
        'hmall-product:fetch',
    ];

    /**
     * 每日排程的步驟，順序即執行順序。
     *
     * 前面的爬蟲失敗不會擋住後面的快取重建與 sitemap：資料庫裡還有昨天的完整資料，
     * 讓快取跟著資料庫走，比讓整條排程斷在第一步安全。
     *
     * 寫成方法而不是 const：const 運算式要到 PHP 8.3 才能取 enum 的 ->value，
     * 而 composer.json 宣告支援的是 ^8.1。
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
     * 執行單一步驟。完全成功回傳 null，否則回傳給通知看的描述加上「是不是
     * 只抓到一部分」。
     *
     * 失敗有兩種形態，兩種都要攔：丟例外，以及安靜回傳非 0 的 exit code
     * （例如 FetchHmallProducts 爬蟲失敗時就是回傳非 0 而不丟例外）。
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
     * 把 exit code 翻成通知裡看得懂的原因。
     *
     * 「部分成功」與「完全失敗」要分開，因為要看的東西不同：部分成功代表資料庫裡
     * 還有昨天的完整資料；完全失敗代表連一頁都沒抓到，通常是被擋。兩種都不會自己
     * 好，差別只在急迫程度。
     *
     * exit code 1 對每個步驟都是整步失敗，所以一律標成「完全失敗」。只有爬蟲指令
     * 的 2 才是部分成功，其他步驟的 2 沒有共同約定，照實寫出來比猜一個意思安全。
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

    /**
     * 這個步驟的 exit code 是不是「抓到一部分」。
     */
    private function isPartialSuccess(string $command, int $exitCode): bool
    {
        return in_array($command, self::PARTIAL_SUCCESS_COMMANDS, true)
            && CrawlOutcome::tryFrom($exitCode) === CrawlOutcome::PartiallySucceeded;
    }

    /**
     * 組出通知裡的一行，例如
     * 「hmall-product:fetch UNIQLO（部分成功：已執行缺貨判定，排除 1 件寫入失敗商品）」。
     *
     * 光看 exit code 分不出「部分成功」底下的差別：目錄有缺頁代表這一輪根本沒做
     * 缺貨判定、站上還是上一次完整掃描的結果，跟排除幾件寫入失敗的商品之後做了
     * 缺貨判定，處理的急迫程度不一樣。指令有留說明就接在結果後面。
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
     * 發出彙整通知。
     *
     * 「整步沒做」與「抓到一部分」要分開算、也要分開送，因為兩者的處理方式
     * 不同：整步沒做代表那件事今天沒發生，要有人去看；抓到一部分代表資料庫
     * 裡還有完整的舊資料，站上不會缺，只要追那幾筆。
     *
     * 混在同一句「N of M scheduled steps failed」、同一封紅色通知的後果是
     * 訓練人忽略通知：只要有一件商品來源資料長期寫不進去，爬蟲每天回部分成功，
     * 就每天收到一封紅字，真正的整步失敗反而被淹掉。
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
