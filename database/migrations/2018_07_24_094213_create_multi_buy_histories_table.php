<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMultiBuyHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('multi_buy_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('product_id')->index()->nullable();
            $table->string('multi_buy')->nullable();
            $table->timestamp('created_at')->useCurrent()->nullable();

            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('multi_buy_histories');
    }
}
