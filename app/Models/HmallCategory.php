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
     * 父分類必定與自己同品牌：兩家的分類樹是各自獨立的。
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_code', 'code')
            ->whereColumn('hmall_categories.brand', 'brand');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_code', 'code')
            ->where('brand', $this->brand);
    }

    public function hmallProducts()
    {
        return $this->belongsToMany(
            HmallProduct::class,
            'hmall_category_hmall_product',
            'hmall_category_id',
            'hmall_product_id'
        )->withPivot('sort');
    }
}
