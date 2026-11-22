<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 官方穿搭資料附的單品辨識結果（品類＋顏色），用來算「搭配單品的顏色」。
     * 空陣列代表官方沒辨識出東西，null 代表沒有這份資料。
     */
    public function up(): void
    {
        Schema::table('style_hints', function (Blueprint $table) {
            $table->json('detected_items')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('style_hints', function (Blueprint $table) {
            $table->dropColumn('detected_items');
        });
    }
};
