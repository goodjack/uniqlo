<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHmallPriceHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hmall_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hmall_product_id')->constrained();
            $table->decimal('min_price', 7, 2)->nullable();
            $table->decimal('max_price', 7, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hmall_price_histories');
    }
}
