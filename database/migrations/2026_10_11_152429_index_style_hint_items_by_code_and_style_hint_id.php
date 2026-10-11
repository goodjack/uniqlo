<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 商品頁用 code 找穿搭再用 style_hint_id 排序或串回去，組合索引讓這段只讀索引。
     * 原本單獨的 code 索引是它的前綴，留著只會多佔記憶體，一起換掉。
     */
    public function up(): void
    {
        Schema::table('style_hint_items', function (Blueprint $table) {
            $table->index(['code', 'style_hint_id']);
        });

        Schema::table('style_hint_items', function (Blueprint $table) {
            $table->dropIndex(['code']);
        });
    }

    public function down(): void
    {
        Schema::table('style_hint_items', function (Blueprint $table) {
            $table->index('code');
        });

        Schema::table('style_hint_items', function (Blueprint $table) {
            $table->dropIndex(['code', 'style_hint_id']);
        });
    }
};
