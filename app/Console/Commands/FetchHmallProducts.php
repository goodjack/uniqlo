<?php

namespace App\Console\Commands;

use App\Enums\CrawlOutcome;
use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Services\HmallProductService;
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
     */
    public function handle(HmallProductService $hmallProductService): int
    {
        $brand = $this->argument('brand');
        $fresh = $this->option('fresh');

        if ($fresh) {
            $this->warn('Starting fresh - ignoring checkpoint');
        }

        $this->info("Fetching Hmall products for {$brand}...");
        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand);

        $outcome = $hmallProductService->fetchAllHmallProducts($brand, $fresh);

        // 只有整批都抓完才算完成，部分成功交給排程彙總成失敗通知
        if ($outcome === CrawlOutcome::Succeeded) {
            AppTaskFinished::dispatch(class_basename(__CLASS__), $brand);
        }

        $this->info("Fetched Hmall products for {$brand}（{$outcome->label()}）");

        return $outcome->value;
    }
}
