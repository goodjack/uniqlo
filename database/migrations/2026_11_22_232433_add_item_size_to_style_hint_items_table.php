<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 穿搭裡這件商品穿的尺寸（例如 M、25inch），用來算「最多人穿的尺寸」。
     */
    public function up(): void
    {
        Schema::table('style_hint_items', function (Blueprint $table) {
            $table->string('item_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('style_hint_items', function (Blueprint $table) {
            $table->dropColumn('item_size');
        });
    }
};
