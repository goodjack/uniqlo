<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 商品與分類的關聯。
     *
     * 掛的是分類的 id 而不是 code：分類的身分是「品牌加 code」，光憑 code
     * 指不到唯一一筆。sort 是官方在該分類內的排序權重（來自 categorySortList），
     * 分類頁照它排就是官網順序。
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmall_category_hmall_product');
    }
};
