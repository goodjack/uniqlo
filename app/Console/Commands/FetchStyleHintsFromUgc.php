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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(StyleHintService $styleHintService)
    {
        parent::__construct();

        $this->styleHintService = $styleHintService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $country = $this->argument('country');

        Log::debug("FetchStyleHintsFromUgc {$country} start");

        $this->styleHintService->fetchAllStyleHintsFromUgc($country);

        Log::debug("FetchStyleHintsFromUgc {$country} end");

        return 0;
    }
}
