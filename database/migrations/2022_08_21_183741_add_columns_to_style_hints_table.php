<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToStyleHintsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('style_hints', function (Blueprint $table) {
            $table->string('user_id')->nullable()->after('model_height');
            $table->string('user_name')->nullable()->after('user_id');
            $table->string('user_image_url')->nullable()->after('user_name');
            $table->string('user_type')->nullable()->after('user_image_url');
            $table->string('store_region')->nullable()->after('user_type');
            $table->string('store_name')->nullable()->after('store_region');
            $table->longText('comment')->nullable()->after('store_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('style_hints', function (Blueprint $table) {
            $table->dropColumn(['user_name', 'user_image_url', 'user_type', 'store_name', 'comment']);
        });
    }
}
