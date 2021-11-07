<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HmallProduct extends Model
{
    protected $fillable = ['product_code'];

    public function hmallPriceHistories()
    {
        return $this->hasMany(HmallPriceHistory::class);
    }

    /**
     * Get the price of the product.
     *
     * @return string
     */
    public function getPriceAttribute()
    {
        return $this->min_price;
    }
}
