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
        Schema::create('japan_products', function (Blueprint $table) {
            $table->id();
            $table->string('brand')->default('UNIQLO');
            $table->string('product_id')->index();
            $table->string('l1Id')->unique();
            $table->string('name')->nullable();
            $table->string('gender_category')->nullable();
            $table->decimal('rating_average', 5, 4)->nullable();
            $table->smallInteger('rating_count')->nullable();
            $table->json('main_images')->nullable();
            $table->json('sub_images')->nullable();
            $table->json('sub_videos')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('japan_products');
    }
};
