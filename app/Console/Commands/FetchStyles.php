<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Services\StyleService;
use Illuminate\Console\Command;

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
        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand);

        $styleService->fetchAllStyles($brand);

        AppTaskFinished::dispatch(class_basename(__CLASS__), $brand);
        $this->info("Fetched styles for {$brand}");

        return 0;
    }
}
