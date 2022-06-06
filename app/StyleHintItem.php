<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StyleHintItem extends Model
{
    protected $fillable = ['style_hint_id', 'code', 'original_product_id'];
    public $timestamps = false;
}
