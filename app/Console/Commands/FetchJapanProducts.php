<?php

namespace App\Console\Commands;

use App\Services\JapanProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchJapanProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'japan-product:fetch {brand=UNIQLO : The brand of the products}';

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

        $this->info("Fetching Japan products for {$brand}...");
        Log::debug("FetchJapanProducts {$brand} start");

        $japanProductService->fetchAllProducts($brand);

        Log::debug("FetchJapanProducts {$brand} end");
        $this->info("Fetched Japan products for {$brand}");

        return 0;
    }
}
