<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['id'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function histories()
    {
        return $this->hasMany('App\ProductHistory');
    }

    public function category()
    {
        return $this->belongsTo('App\Category');
    }

    public function styleDictionaries()
    {
        return $this->belongsToMany('App\StyleDictionary');
    }
}
