<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Services\HmallProductService;
use Illuminate\Console\Command;

class FetchHmallProductDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product-description:fetch {brand=UNIQLO : The brand of the products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all Hmall Product descriptions from SPU';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(HmallProductService $hmallProductService)
    {
        $brand = $this->argument('brand');

        $this->info("Fetching Hmall product descriptions for {$brand}...");
        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand);

        $hmallProductService->fetchAllHmallProductDescriptions($brand);

        AppTaskFinished::dispatch(class_basename(__CLASS__), $brand);
        $this->info("Fetched Hmall product descriptions for {$brand}");

        return 0;
    }
}
