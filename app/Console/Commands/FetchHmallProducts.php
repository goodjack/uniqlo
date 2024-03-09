<?php

namespace App\Console\Commands;

use App\Services\HmallProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchHmallProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product:fetch {brand=UNIQLO : The brand of the products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all products from Hmall';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(HmallProductService $hmallProductService)
    {
        $brand = $this->argument('brand');

        $this->info("Fetching Hmall products for {$brand}...");
        Log::debug("FetchHmallProducts {$brand} start");

        $hmallProductService->fetchAllHmallProducts($brand);

        Log::debug("FetchHmallProducts {$brand} end");
        $this->info("Fetched Hmall products for {$brand}");

        return 0;
    }
}
