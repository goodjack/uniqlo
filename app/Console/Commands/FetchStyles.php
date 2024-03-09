<?php

namespace App\Console\Commands;

use App\Services\StyleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchStyles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'style:fetch {brand=UNIQLO : The brand of the styles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all styles from Official Styling';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(StyleService $styleService)
    {
        $brand = $this->argument('brand');

        $this->info("Fetching styles for {$brand}...");
        Log::debug("FetchStyles {$brand} start");

        $styleService->fetchAllStyles($brand);

        Log::debug("FetchStyles {$brand} end");
        $this->info("Fetched styles for {$brand}");

        return 0;
    }
}
