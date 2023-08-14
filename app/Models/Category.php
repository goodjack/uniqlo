<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['id'];

    public $incrementing = false;

    protected $keyType = 'string';

    public function products()
    {
        return $this->hasMany('App\Models\Product');
    }
}
