<?php

namespace App\Console\Commands;

use App\Services\StyleHintService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

        Log::debug("FetchStyleHints {$country} start");

        $this->styleHintService->fetchAllStyleHints($country);

        Log::debug("FetchStyleHints {$country} end");

        return 0;
    }
}
