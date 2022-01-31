<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeProductNameColumnTypeInHmallProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hmall_products', function (Blueprint $table) {
            $table->text('product_name')->nullable()->change();
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
            $table->string('product_name')->nullable()->change();
        });
    }
}
