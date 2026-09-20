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
     *
     * 這支必須排在 create_hmall_categories_table 之後執行，因為外鍵指到它。
     * 兩支目前共用同一個時間戳，順序靠檔名字典序（categories 在 category_
     * 之前）。要改動其中一支的檔名或時間戳時，這個順序要一起顧到。
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

            // 商品或分類被刪掉時，關聯要跟著走。所有讀取路徑都是 inner join，
            // 孤兒列不會讓任何畫面出錯，只會無聲累積到沒有人發現。
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

    /**
     * 整張表刪掉，外鍵會跟著一起消失，這就是 up() 的反向操作。
     *
     * 沒有另外寫 dropForeign：對 create 型的 migration 來說它不會多做任何事，
     * 反而在外鍵名稱跟預期不一致時讓 rollback 整個失敗。
     */
    public function down(): void
    {
        Schema::dropIfExists('hmall_category_hmall_product');
    }
};
