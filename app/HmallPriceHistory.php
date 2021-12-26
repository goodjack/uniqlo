<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HmallPriceHistory extends Model
{
    public function hmallProduct()
    {
        return $this->belongsTo(HmallProduct::class);
    }
}
