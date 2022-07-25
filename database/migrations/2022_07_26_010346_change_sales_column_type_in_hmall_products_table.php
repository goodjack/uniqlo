<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeSalesColumnTypeInHmallProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hmall_products', function (Blueprint $table) {
            $table->unsignedInteger('sales')->change();
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
            $table->unsignedSmallInteger('sales')->change();
        });
    }
}
