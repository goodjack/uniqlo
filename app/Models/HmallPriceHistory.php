<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HmallPriceHistory extends Model
{
    use HasFactory;

    public function hmallProduct()
    {
        return $this->belongsTo(HmallProduct::class);
    }
}
