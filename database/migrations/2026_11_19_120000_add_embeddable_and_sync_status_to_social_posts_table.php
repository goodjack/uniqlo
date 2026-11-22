<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            // 無法嵌入的貼文，平台的嵌入只會顯示「無法顯示」；記下來，點開時直接顯示站內卡片
            $table->boolean('embeddable')->default(true)->after('cover_expires_at');
            // 上次抓取的結果，寫回 Notion「同步狀態」用；存在這裡，寫回失敗時下一輪不用重抓也能補送。重複與否每輪另算，不存
            $table->string('sync_status', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn(['embeddable', 'sync_status']);
        });
    }
};
