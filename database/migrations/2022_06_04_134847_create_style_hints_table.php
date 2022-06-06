<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStyleHintsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('style_hints', function (Blueprint $table) {
            $table->id();
            $table->string('outfit_id');
            $table->string('country');
            $table->string('style_image_url')->nullable();
            $table->string('original_source_url')->nullable();
            $table->string('department_id')->nullable();
            $table->string('model_height')->nullable();
            $table->json('user_info')->nullable();
            $table->json('hashtags')->nullable();
            $table->integer('gender')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['outfit_id', 'country']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('style_hints');
    }
}
