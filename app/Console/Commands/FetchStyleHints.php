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
    protected $signature = 'style-hint:fetch {country}
        {--fresh : Ignore checkpoint and start fresh}
        {--backfill : Resume from checkpoint without early termination}';

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
        $fresh = $this->option('fresh');
        $backfill = $this->option('backfill');

        if ($fresh && ! $backfill) {
            $this->warn('--fresh has no effect without --backfill (daily mode always starts from 0)');
        }

        if ($fresh && $backfill) {
            $this->warn('Starting fresh backfill - clearing checkpoint');
        }

        if ($backfill) {
            $this->info("Backfilling style hints for {$country}...");
        } else {
            $this->info("Fetching style hints for {$country}...");
        }

        AppTaskStarting::dispatch(class_basename(__CLASS__), null, $country);

        $succeeded = $styleHintService->fetchAllStyleHints($country, $fresh, $backfill);

        if ($succeeded) {
            AppTaskFinished::dispatch(class_basename(__CLASS__), null, $country);
        }

        $this->info("Fetched style hints for {$country}");

        return $succeeded ? 0 : 1;
    }
}
