<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 一則貼文可以介紹好幾件商品。對的是貨號（hmall_products.code），
     * 同一個貨號可能對到好幾個商品頁，都要顯示。
     */
    public function up(): void
    {
        Schema::create('social_post_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('social_post_id');
            $table->string('code', 20);
            // 影片講到這件商品的秒數（Notion 寫「貨號@1:23」），目前只有 YouTube 用得上
            $table->unsignedInteger('start_seconds')->nullable();

            $table->unique(['social_post_id', 'code']);
            $table->index('code');

            $table->foreign('social_post_id')
                ->references('id')
                ->on('social_posts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_codes');
    }
};
