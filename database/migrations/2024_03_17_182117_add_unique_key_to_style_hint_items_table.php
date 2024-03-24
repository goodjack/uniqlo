<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('style_hint_items', function (Blueprint $table) {
            $table->unique(['style_hint_id', 'code', 'original_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('style_hint_items', function (Blueprint $table) {
            $table->dropUnique(['style_hint_id', 'code', 'original_product_id']);
        });
    }
};
