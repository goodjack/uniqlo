<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StyleDictionary extends Model
{
    use HasFactory;

    protected $fillable = ['id'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function products()
    {
        return $this->belongsToMany('App\Models\Product');
    }
}
