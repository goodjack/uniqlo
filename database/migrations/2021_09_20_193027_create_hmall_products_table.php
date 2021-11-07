<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHmallProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hmall_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->index();
            $table->string('product_code')->nullable()->index();
            $table->string('oms_product_code')->nullable()->comment('貨號');
            $table->string('name')->nullable()->comment('商品名稱');
            $table->string('product_name')->nullable();
            $table->json('prices')->nullable()->comment('售價');
            $table->decimal('min_price', 7, 2)->nullable();
            $table->decimal('max_price', 7, 2)->nullable();
            $table->decimal('origin_price', 7, 2)->nullable()->comment('原價');
            $table->decimal('lowest_record_price', 7, 2)->nullable()->comment('歷史低價');
            $table->decimal('highest_record_price', 7, 2)->nullable()->comment('歷史高價');
            $table->string('price_color')->nullable();
            $table->json('identity')->nullable()->comment('商品標識');
            $table->string('label')->nullable();
            $table->decimal('score', 5, 4)->nullable()->comment('商品評分');
            $table->unsignedSmallInteger('size_score')->nullable()->comment('商品尺寸評分');
            $table->unsignedSmallInteger('evaluation_count')->nullable()->comment('商品評論數');
            $table->unsignedSmallInteger('sales')->nullable()->comment('銷量');
            $table->string('new')->nullable()->comment('新到商品');
            $table->string('season')->nullable()->comment('上市季節');
            $table->json('style_text')->nullable()->comment('顏色');
            $table->json('color_nums')->nullable();
            $table->json('color_pic')->nullable();
            $table->json('chip_pic')->nullable();
            $table->json('main_pic')->nullable();
            $table->string('main_first_pic')->nullable()->comment('主圖');
            $table->json('size')->nullable()->comment('尺寸');
            $table->string('min_size')->nullable();
            $table->string('max_size')->nullable();
            $table->string('gender')->nullable()->comment('適用性別');
            $table->string('sex')->nullable();
            $table->string('material')->nullable()->comment('材質');
            $table->string('stock')->nullable();
            $table->text('instruction')->comment('商品說明')->nullable();
            $table->text('size_chart')->comment('商品尺寸')->nullable();
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
        Schema::dropIfExists('hmall_products');
    }
}
