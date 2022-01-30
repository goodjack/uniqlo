<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimeLimitedColumnsToHmallProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hmall_products', function (Blueprint $table) {
            $table->timestamp('time_limited_begin')->nullable()->after('label');
            $table->timestamp('time_limited_end')->nullable()->after('time_limited_begin');
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
            $table->dropColumn(['time_limited_begin', 'time_limited_end']);
        });
    }
}
