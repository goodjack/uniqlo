<?php

namespace App\Console\Commands;

use App\Enums\CrawlOutcome;
use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Services\HmallProductService;
use App\Support\TaskNotes;
use Illuminate\Console\Command;

class FetchHmallProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product:fetch {brand=UNIQLO : The brand of the products} {--fresh : Ignore checkpoint and start fresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all products from Hmall';

    /**
     * Execute the console command.
     *
     * 回傳值直接用 CrawlOutcome 的值當 exit code，讓排程在通知裡分得出
     * 「整支掛掉」與「抓到一部分」。
     *
     * exit code 只有三種，但「抓到一部分」底下有好幾種狀況，急迫程度不一樣：
     * 目錄有缺頁代表這一輪沒做缺貨判定、站上還是上一次完整掃描的結果；排除幾件
     * 寫入失敗的商品之後做了缺貨判定是另一回事。所以另外把這一輪的說明留給排程，
     * 讓彙整出來的通知寫得出差別。
     */
    public function handle(HmallProductService $hmallProductService, TaskNotes $taskNotes): int
    {
        $brand = $this->argument('brand');
        $fresh = $this->option('fresh');

        if ($fresh) {
            $this->warn('Starting fresh - ignoring checkpoint');
        }

        $this->info("Fetching Hmall products for {$brand}...");
        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand);

        $result = $hmallProductService->fetchAllHmallProducts($brand, $fresh);

        // 只有整批都抓完才算完成，部分成功交給排程彙總成失敗通知
        if ($result->outcome === CrawlOutcome::Succeeded) {
            AppTaskFinished::dispatch(class_basename(__CLASS__), $brand);
        } elseif ($result->note !== null) {
            $taskNotes->put($this->getName(), $brand, $result->note);
        }

        $this->info("Fetched Hmall products for {$brand}（{$result->describe()}）");

        return $result->outcome->value;
    }
}
