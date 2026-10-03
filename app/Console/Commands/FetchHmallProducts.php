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
     * exit code 就是 CrawlOutcome 的值；這一輪的說明另外留給排程的彙整通知。
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

        // 部分成功也要送結束通知並附說明：手動執行時沒有排程彙整，
        // 不送的話 Discord 上 start 之後就沒有下文
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
