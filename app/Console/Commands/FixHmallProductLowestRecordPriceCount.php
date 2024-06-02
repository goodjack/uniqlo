<?php

namespace App\Console\Commands;

use App\Models\HmallPriceHistory;
use App\Models\HmallProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixHmallProductLowestRecordPriceCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product:fix-lowest-record-price-count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix lowest record price count for Hmall products.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lowestPricesQuery = HmallPriceHistory::query()
            ->select('hmall_product_id', DB::raw('MIN(min_price) as lowest_record_price'))
            ->groupBy('hmall_product_id');

        HmallProduct::query()
            ->joinSub($lowestPricesQuery, 'lowest_prices', function ($join) {
                $join->on('hmall_products.id', '=', 'lowest_prices.hmall_product_id');
            })
            ->update([
                'lowest_record_price_count' => DB::raw('(
                    SELECT COUNT(*)
                    FROM hmall_price_histories
                    WHERE hmall_price_histories.hmall_product_id = hmall_products.id
                    AND hmall_price_histories.min_price = lowest_prices.lowest_record_price
                )'),
            ]);
    }
}
