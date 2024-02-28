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
            $table->after('rating_count', function (Blueprint $table) {
                $table->json('prices')->nullable();
                $table->decimal('lowest_record_price', 7, 2)->nullable()->comment('歷史低價');
                $table->decimal('highest_record_price', 7, 2)->nullable()->comment('歷史高價');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('japan_products', function (Blueprint $table) {
            $table->dropColumn(['prices', 'lowest_record_price', 'highest_record_price']);
        });
    }
};
