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
        Schema::table('style_hints', function (Blueprint $table) {
            $table->string('brand')->default('UNIQLO')->after('outfit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('style_hints', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }
};
