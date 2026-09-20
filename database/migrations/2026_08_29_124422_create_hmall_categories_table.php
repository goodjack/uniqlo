<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 官方分類主檔。
     *
     * 分類的身分是「品牌加 code」，不是 code 本身：兩家各有一套命名
     * （UNIQLO 是 all_men-tops、GU 是 men_outer_blouson），目前只有頂層的 ALL
     * 兩家共用，但沒有任何保證未來不會撞。用 code 單獨當鍵的話，一撞就是後爬到的
     * 那家覆蓋掉先爬到的。
     */
    public function up(): void
    {
        Schema::create('hmall_categories', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('code');
            $table->string('name');
            // 根節點（ALL、all_men、men_all 等）沒有父分類。父分類必定同品牌。
            $table->string('parent_code')->nullable();
            // 0 = topCategories、1 = levelOne、2 = levelTwo、3 = levelThree
            $table->unsignedTinyInteger('level');
            $table->timestamps();

            $table->unique(['brand', 'code']);
            $table->index(['brand', 'parent_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmall_categories');
    }
};
