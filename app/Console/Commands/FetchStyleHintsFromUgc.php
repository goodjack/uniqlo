<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Services\StyleHintService;
use Illuminate\Console\Command;

class FetchStyleHintsFromUgc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'style-hint-ugc:fetch {brand=UNIQLO : The brand of the products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all style hints from UGC service';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(StyleHintService $styleHintService)
    {
        $brand = $this->argument('brand');

        $this->info("Fetching style hints for {$brand}...");
        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand);

        $styleHintService->fetchAllStyleHintsFromUgc($brand, true);

        AppTaskFinished::dispatch(class_basename(__CLASS__), $brand);
        $this->info("Fetched style hints for {$brand}");

        return 0;
    }
}
