<?php

use App\Models\HmallPriceHistory;
use App\Models\HmallProduct;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hmall_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('lowest_record_price_count')->default(0)->after('highest_record_price');
        });

        $lowestPricesQuery = HmallPriceHistory::query()
            ->select('hmall_product_id', DB::raw('MIN(min_price) as lowest_record_price'))
            ->groupBy('hmall_product_id');

        HmallProduct::query()
            ->where('lowest_record_price_count', 0)
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hmall_products', function (Blueprint $table) {
            $table->dropColumn('lowest_record_price_count');
        });
    }
};
