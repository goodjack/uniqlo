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
    protected $signature = 'style-hint-ugc:fetch
                            {brand=UNIQLO : The brand of the products}
                            {--only-recent : Fetch only recent style hints}
                            {--is-scheduled : Is scheduled task}';

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
        $onlyRecent = $this->option('only-recent');
        $isManual = ! ($this->option('is-scheduled'));

        $this->info($onlyRecent ? 'Only recent style hints will be fetched.' : 'All style hints will be fetched.');
        $this->info($isManual ? 'This is a manual task.' : null);

        $this->newLine();

        $this->info("Fetching style hints for {$brand}...");

        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand, null, [
            'onlyRecent' => $onlyRecent,
            'isManual' => $isManual,
        ]);

        $styleHintService->fetchAllStyleHintsFromUgc($brand, $onlyRecent, $isManual);

        AppTaskFinished::dispatch(class_basename(__CLASS__), $brand, null, [
            'onlyRecent' => $onlyRecent,
            'isManual' => $isManual,
        ]);
        $this->info("Fetched style hints for {$brand}.");

        return 0;
    }
}
