<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 商品與分類的關聯。掛分類的 id 而不是 code：光憑 code 指不到唯一一筆。
     * sort 是官方在該分類內的排序權重（categorySortList）。
     *
     * 外鍵指到 hmall_categories，這支必須在它之後執行。兩支共用同一個時間戳，
     * 順序靠檔名字典序，改檔名時要顧到。
     */
    public function up(): void
    {
        Schema::create('hmall_category_hmall_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hmall_product_id');
            $table->unsignedBigInteger('hmall_category_id');
            $table->string('sort')->nullable();

            $table->unique(
                ['hmall_product_id', 'hmall_category_id'],
                'hmall_category_product_unique'
            );
            $table->index('hmall_category_id');

            $table->foreign('hmall_product_id')
                ->references('id')
                ->on('hmall_products')
                ->cascadeOnDelete();
            $table->foreign('hmall_category_id')
                ->references('id')
                ->on('hmall_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmall_category_hmall_product');
    }
};
