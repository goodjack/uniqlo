<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddMainColorAndReviewRatingToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('main_color')->nullable()->after('main_image_url');
            $table->string('review_rating')->nullable()->after('review_count');
        });

        DB::table('products')->chunkById(100, function ($products) {
            foreach ($products as $product) {
                $mainImageUrl = $product->main_image_url;

                $matches = [];
                preg_match('/\/item\/(.*)_/', $mainImageUrl, $matches);
                $mainColor = $matches[1];

                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['main_color' => $mainColor]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['main_color', 'review_rating']);
        });
    }
}
