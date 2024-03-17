<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Services\StyleHintService;
use Illuminate\Console\Command;

class FetchStyleHints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'style-hint:fetch {country}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all style hints from UNIQLO';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(StyleHintService $styleHintService)
    {
        $country = $this->argument('country');

        $this->info("Fetching style hints for {$country}...");
        AppTaskStarting::dispatch(class_basename(__CLASS__), null, $country);

        $styleHintService->fetchAllStyleHints($country);

        AppTaskFinished::dispatch(class_basename(__CLASS__), null, $country);
        $this->info("Fetched style hints for {$country}");

        return 0;
    }
}
