<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeColumnsNullableInCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('image')->nullable()->change();
            $table->integer('level')->nullable()->change();
            $table->integer('weight')->nullable()->change();
            $table->string('parent_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('image')->nullable(false)->change();
            $table->integer('level')->nullable(false)->change();
            $table->integer('weight')->nullable(false)->change();
            $table->string('parent_id')->nullable(false)->change();
        });
    }
}
