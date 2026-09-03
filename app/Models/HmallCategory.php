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
     *
     * 條件用 $this->brand 這個實際的值，跟 children() 一致。原本寫的
     * whereColumn('hmall_categories.brand', 'brand') 是同一張表拿自己跟自己比，
     * 恆為真、等於沒有限制；兩家撞到同一個 parent_code 時會撈到另一家的分類。
     *
     * 代價是這個關聯不能用 with()／load() 一次撈：eager load 只會拿集合裡第一筆
     * 的品牌去套全部。要一次取多筆的呼叫端請自己按品牌分組後再逐組取。
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_code', 'code')
            ->where('brand', $this->brand);
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
