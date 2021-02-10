<?php

namespace App\Models;

use App\Models\Category;
use App\Models\MultiBuyHistory;
use App\Models\ProductHistory;
use App\Models\Style;
use App\Models\StyleDictionary;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['id'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function histories()
    {
        return $this->hasMany(ProductHistory::class);
    }

    public function multiBuys()
    {
        return $this->hasMany(MultiBuyHistory::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function styleDictionaries()
    {
        return $this->belongsToMany(StyleDictionary::class);
    }

    public function styles()
    {
        return $this->belongsToMany(Style::class);
    }

    /**
     * Get the reco image (180x180) of the product.
     *
     * @return string
     */
    public function getRecoImageUrlAttribute()
    {
        $id = $this->id;
        $color = $this->main_color;

        return "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$color}_{$id}_reco.jpg";
    }

    /**
     * Get the middles image (228x228) of the product.
     *
     * @return string
     */
    public function getMiddlesImageUrlAttribute()
    {
        $id = $this->id;
        $color = $this->main_color;

        return "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$color}_{$id}_middles.jpg";
    }
}
