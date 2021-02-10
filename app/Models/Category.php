<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['id'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
