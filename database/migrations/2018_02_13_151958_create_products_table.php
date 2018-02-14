<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->json('categories')->nullable();
            $table->json('ancestors')->nullable();
            $table->string('main_image_url')->nullable();
            $table->text('comment')->nullable();
            $table->integer('price')->nullable();
            $table->json('flags')->nullable();
            $table->string('limit_sales_end_msg')->nullable();
            $table->boolean('new')->nullable();
            $table->json('sub_images')->nullable();
            $table->json('style_dicrionary_images')->nullable();
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
        Schema::dropIfExists('products');
    }
}
