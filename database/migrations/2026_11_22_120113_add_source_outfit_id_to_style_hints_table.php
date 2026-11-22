<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 存同一則穿搭各國版本共用的原始穿搭編號，用來判斷是不是同一則。
     */
    public function up(): void
    {
        Schema::table('style_hints', function (Blueprint $table) {
            $table->string('source_outfit_id')->nullable()->index();
            $table->index(['id', 'source_outfit_id']);
        });
    }

    public function down(): void
    {
        Schema::table('style_hints', function (Blueprint $table) {
            $table->dropIndex(['id', 'source_outfit_id']);
            $table->dropColumn('source_outfit_id');
        });
    }
};
