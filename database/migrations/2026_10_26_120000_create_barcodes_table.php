<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 吊牌條碼對到的官方商品，只存查到的；查不到的是暫時結論，放快取讓它自己過期。
     * 條碼對商品的關係不會變，存了就一直有效。不跟商品表建外鍵：掃的當下才對到
     * 網站的商品頁，網站還沒收錄的也要存。
     */
    public function up(): void
    {
        Schema::create('barcodes', function (Blueprint $table) {
            $table->id();
            $table->char('barcode', 13)->unique();
            // tw 或 jp：哪一區的官網查到的
            $table->string('region', 2);
            // 值見 App\Enums\Brand
            $table->string('brand');
            // 台灣同 hmall_products.product_code；日本是 E470077-000 這種
            $table->string('product_code');
            // 6 位數吊牌編號，日本可能不只一個
            $table->json('codes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcodes');
    }
};
