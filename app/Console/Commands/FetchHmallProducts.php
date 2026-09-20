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
     * 讓彙整出來的通知寫得出差別，手動執行時也接在結束通知裡。
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

        if ($result->note !== null) {
            $taskNotes->put($this->getName(), $brand, $result->note);
        }

        // 只有完全失敗才不送結束通知。TaskNotes 只活在同一個 process 裡，手動執行
        // 沒有排程來取走說明，部分成功若也不送結束通知，Discord 上就是 start 之後
        // 什麼都沒有——看的人分不出是還在跑、卡住、還是掛了。說明接在通知資料裡，
        // 這樣手動執行也看得出這一輪到底有沒有做缺貨判定。
        if ($result->outcome !== CrawlOutcome::Failed) {
            AppTaskFinished::dispatch(
                class_basename(__CLASS__),
                $brand,
                null,
                $result->note === null ? null : ['result' => $result->describe()],
            );
        }

        $this->info("Fetched Hmall products for {$brand}（{$result->describe()}）");

        return $result->outcome->value;
    }
}
