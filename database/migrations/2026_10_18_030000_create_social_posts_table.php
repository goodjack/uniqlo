<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 社群熱推：Notion 清單同步進來的社群貼文。清單本身在 Notion，這裡是抓好的快取，
     * 商品頁只讀這張表，不在訪客請求時打外部 API。
     */
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('notion_page_id')->unique();
            $table->timestamp('notion_created_at');
            // 跟 Notion 的 last_edited_time 比對，沒變就不重抓
            $table->timestamp('notion_edited_at');
            $table->integer('sort')->nullable();
            $table->text('source_url');
            $table->text('canonical_url')->nullable();
            $table->string('platform', 20)->nullable();
            $table->string('kind', 20)->nullable();
            $table->string('author')->nullable();
            $table->text('text')->nullable();
            $table->string('youtube_id', 11)->nullable();
            // IG、Threads、X 頁面 og:image 的網址，不存圖；顯示時經自己的網址轉送，讓 CDN 快取
            $table->text('cover_url')->nullable();
            // Meta 的預覽圖網址帶簽章，幾天後失效，快到期就重抓；X 的沒有期限，留空
            $table->timestamp('cover_expires_at')->nullable();
            $table->string('status', 10)->default('ok');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
