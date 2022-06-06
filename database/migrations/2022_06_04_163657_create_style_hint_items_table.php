<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStyleHintItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('style_hint_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('style_hint_id')->constrained();
            $table->string('code')->index();
            $table->string('original_product_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('style_hint_items');
    }
}
