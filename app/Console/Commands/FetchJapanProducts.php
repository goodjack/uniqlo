<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Services\JapanProductService;
use Illuminate\Console\Command;

class FetchJapanProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'japan-product:fetch {brand=UNIQLO : The brand of the products} {--fresh : Ignore checkpoint and start fresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all Japan products';

    /**
     * Execute the console command.
     */
    public function handle(JapanProductService $japanProductService)
    {
        $brand = $this->argument('brand');
        $fresh = $this->option('fresh');

        if ($fresh) {
            $this->warn('Starting fresh - ignoring checkpoint');
        }

        $this->info("Fetching Japan products for {$brand}...");
        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand);

        $japanProductService->fetchAllProducts($brand, $fresh);

        AppTaskFinished::dispatch(class_basename(__CLASS__), $brand);
        $this->info("Fetched Japan products for {$brand}");

        return 0;
    }
}
