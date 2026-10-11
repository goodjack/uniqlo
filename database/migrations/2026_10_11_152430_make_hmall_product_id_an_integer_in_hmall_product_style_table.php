<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 查詢帶的是整數，欄位卻是字串，型別不同就用不到索引、整張表掃過。
     * style_id 維持字串，styles 的主鍵本來就是字串。
     */
    public function up(): void
    {
        Schema::table('hmall_product_style', function (Blueprint $table) {
            $table->unsignedBigInteger('hmall_product_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('hmall_product_style', function (Blueprint $table) {
            $table->string('hmall_product_id')->change();
        });
    }
};
