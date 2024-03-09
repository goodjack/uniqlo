<?php

namespace App\Console\Commands;

use App\Services\StyleHintService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchStyleHintsFromUgc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'style-hint-ugc:fetch {country}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all style hints from UNIQLO UGC service';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(StyleHintService $styleHintService)
    {
        $country = $this->argument('country');

        $this->info("Fetching style hints for {$country}...");
        Log::debug("FetchStyleHintsFromUgc {$country} start");

        $styleHintService->fetchAllStyleHintsFromUgc($country);

        Log::debug("FetchStyleHintsFromUgc {$country} end");
        $this->info("Fetched style hints for {$country}");

        return 0;
    }
}
