<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Style extends Model
{
    protected $fillable = ['id'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function products()
    {
        return $this->belongsToMany('App\Product');
    }

    public function hmallProducts()
    {
        return $this->belongsToMany(HmallProduct::class);
    }

    public function getLargeImageUrlAttribute()
    {
        return "https://im.uniqlo.com/style/{$this->image_path}-xxxl.jpg";
    }
}
