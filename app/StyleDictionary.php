<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StyleDictionary extends Model
{
    protected $fillable = ['id'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function products()
    {
        return $this->belongsToMany('App\Product');
    }
}
