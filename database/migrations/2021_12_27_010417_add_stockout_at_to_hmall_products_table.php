<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockoutAtToHmallProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hmall_products', function (Blueprint $table) {
            $table->timestamp('stockout_at')->nullable()->after('size_chart');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hmall_products', function (Blueprint $table) {
            $table->dropColumn('stockout_at');
        });
    }
}
