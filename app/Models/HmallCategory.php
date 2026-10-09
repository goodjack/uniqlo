<?php

namespace App\Models;

use App\Enums\Brand;
use App\Enums\CategoryLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HmallCategory extends Model
{
    use HasFactory;

    protected $fillable = ['brand', 'code', 'name', 'parent_code', 'level'];

    protected $casts = [
        'brand' => Brand::class,
        'level' => CategoryLevel::class,
    ];

    /**
     * 父分類必定同品牌（兩家的分類樹各自獨立）。
     *
     * 條件綁的是 $this->brand 的值，所以不能用 with()／load() 一次撈：
     * eager load 會拿第一筆的品牌套全部。
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_code', 'code')
            ->where('brand', $this->brand);
    }
}
