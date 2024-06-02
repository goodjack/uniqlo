<?php

namespace App\Console\Commands;

use App\Models\HmallPriceHistory;
use Illuminate\Console\Command;

class RemoveDuplicatePriceHistories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price-history:remove-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate Hmall price histories from the database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Removing duplicate Hmall price histories...');
        logger()->info('Removing duplicate Hmall price histories...');

        $priceHistories = HmallPriceHistory::query()
            ->orderBy('hmall_product_id')
            ->orderBy('created_at')
            ->get();

        $deletedPriceHistoryIds = [];
        $affectedHmallProductIds = [];

        $previousPriceHistory = null;
        foreach ($priceHistories as $priceHistory) {
            if (
                $previousPriceHistory !== null
                && $priceHistory->hmall_product_id === $previousPriceHistory->hmall_product_id
                && $priceHistory->min_price === $previousPriceHistory->min_price
                && $priceHistory->max_price === $previousPriceHistory->max_price
            ) {
                $deletedPriceHistoryIds[] = $priceHistory->id;
                $affectedHmallProductIds[$priceHistory->hmall_product_id] = true;

                $priceHistory->delete();
            } else {
                $previousPriceHistory = $priceHistory;
            }
        }

        $this->info('Deleted ' . count($deletedPriceHistoryIds) . ' duplicate price histories.');
        $this->info('Affected ' . count($affectedHmallProductIds) . ' Hmall product IDs.');
        logger()->info('Deleted ' . count($deletedPriceHistoryIds) . ' duplicate price histories.');
        logger()->info('Affected ' . count($affectedHmallProductIds) . ' Hmall product IDs.');
        logger()->info('Deleted Hmall price history IDs: ' . implode(',', $deletedPriceHistoryIds));
        logger()->info('Affected Hmall product IDs: ' . implode(',', array_keys($affectedHmallProductIds)));
    }
}
