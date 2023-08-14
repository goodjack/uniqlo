<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StyleHintItem extends Model
{
    use HasFactory;

    protected $fillable = ['style_hint_id', 'code', 'original_product_id'];

    public $timestamps = false;
}
