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
        Schema::table('japan_products', function (Blueprint $table) {
            $table->timestamp('stockout_at')->nullable()->after('sub_videos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('japan_products', function (Blueprint $table) {
            $table->dropColumn('stockout_at');
        });
    }
};
