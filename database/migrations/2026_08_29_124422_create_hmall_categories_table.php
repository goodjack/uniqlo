<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 官方分類主檔。身分是「品牌加 code」：兩家各有一套命名，目前只有頂層的
     * ALL 同名，但沒有保證不會撞。
     *
     * code 與 parent_code 用逐位元比對：預設的 utf8mb4_unicode_ci 不分大小寫、
     * 還忽略零寬空白，而官方真的有尾巴帶 U+200B 的 code，唯一鍵會把兩個不同的
     * code 當成同一筆。
     */
    public function up(): void
    {
        Schema::create('hmall_categories', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('code')->collation('utf8mb4_bin');
            $table->string('name');
            // 根節點（ALL、all_men 等）沒有父分類；父分類必定同品牌
            $table->string('parent_code')->collation('utf8mb4_bin')->nullable();
            // 值見 App\Enums\CategoryLevel
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
