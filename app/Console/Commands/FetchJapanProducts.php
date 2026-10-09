<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Services\JapanProductService;
use App\Support\TaskNotes;
use Illuminate\Console\Command;

class FetchJapanProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'japan-product:fetch {brand=UNIQLO : The brand of the products} {--fresh : Ignore checkpoint and start fresh} {--accept-shrink : Run stockout even if far fewer products than in stock were seen}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all Japan products';

    /**
     * Execute the console command.
     */
    public function handle(JapanProductService $japanProductService, TaskNotes $taskNotes): int
    {
        $brand = $this->argument('brand');
        $fresh = $this->option('fresh');

        if ($fresh) {
            $this->warn('Starting fresh - ignoring checkpoint');
        }

        $this->info("Fetching Japan products for {$brand}...");
        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand);

        $result = $japanProductService->fetchAllProducts($brand, $fresh, $this->option('accept-shrink'));

        if ($result->note !== null) {
            $taskNotes->put($this->getName(), $brand, $result->note);
        }

        if (! $result->outcome->needsAttention()) {
            AppTaskFinished::dispatch(
                class_basename(__CLASS__),
                $brand,
                null,
                $result->note === null ? null : ['result' => $result->describe()],
            );
        }

        $this->info("Fetched Japan products for {$brand}（{$result->describe()}）");

        return $result->outcome->value;
    }
}
