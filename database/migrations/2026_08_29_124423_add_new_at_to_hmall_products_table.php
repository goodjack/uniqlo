<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 上架時間。
     *
     * 官方 API 的 new 欄位是 epoch 毫秒，現在原樣存成字串沒辦法比較大小，
     * 另開一個 datetime 欄位存轉換後的值。
     */
    public function up(): void
    {
        Schema::table('hmall_products', function (Blueprint $table) {
            $table->dateTime('new_at')->nullable()->after('new')->index();
        });
    }

    public function down(): void
    {
        Schema::table('hmall_products', function (Blueprint $table) {
            $table->dropIndex(['new_at']);
            $table->dropColumn('new_at');
        });
    }
};
