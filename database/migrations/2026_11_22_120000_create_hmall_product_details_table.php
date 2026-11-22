<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 商品頁的相簿、影片與商品資訊。另開一張表：清單頁用不到這些大欄位。
     * 時間記在這裡，不動 hmall_products.updated_at（缺貨判定靠它）。
     */
    public function up(): void
    {
        Schema::create('hmall_product_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hmall_product_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('main_images')->nullable();
            $table->json('videos')->nullable();
            // 五星到一星各幾則
            $table->json('rating_levels')->nullable();
            $table->string('origin_place')->nullable();
            $table->json('features')->nullable();
            $table->json('material_care')->nullable();
            $table->char('fingerprint', 40)->nullable();
            $table->json('validators')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('next_fetch_at')->nullable()->index();
            $table->timestamp('stale_since')->nullable()->index();
            $table->timestamp('content_changed_at')->nullable();
            // 連續失敗幾次；失敗後 next_fetch_at 往後推，免得每輪都卡在同幾件
            $table->unsignedTinyInteger('failure_count')->default(0);
            $table->timestamp('last_failed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmall_product_details');
    }
};
