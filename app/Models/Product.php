<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['id'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function histories()
    {
        return $this->hasMany('App\Models\ProductHistory');
    }

    public function multiBuys()
    {
        return $this->hasMany('App\Models\MultiBuyHistory');
    }

    public function category()
    {
        return $this->belongsTo('App\Models\Category');
    }

    public function styleDictionaries()
    {
        return $this->belongsToMany('App\Models\StyleDictionary');
    }

    public function styles()
    {
        return $this->belongsToMany('App\Models\Style');
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

    /**
     * Get the product route url.
     *
     * @return string
     */
    public function getRouteUrlAttribute()
    {
        return route('products.show', ['product' => $this->id]);
    }
}
