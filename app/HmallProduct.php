<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HmallProduct extends Model
{
    protected $fillable = ['product_code'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'time_limited_begin' => 'datetime',
        'time_limited_end' => 'datetime',
        'stockout_at' => 'datetime',
    ];

    public function hmallPriceHistories()
    {
        return $this->hasMany(HmallPriceHistory::class);
    }

    /**
     * Get the price of the product.
     *
     * @return int
     */
    public function getPriceAttribute()
    {
        return (int) $this->min_price;
    }

    /**
     * Get whether the product is online special or not.
     *
     * @return bool
     */
    public function getIsOnlineSpecialAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('ONLINE SPECIAL', $identity);
    }

    /**
     * Get whether the product is multi buy or not.
     *
     * @return bool
     */
    public function getIsMultiBuyAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('SET', $identity);
    }

    /**
     * Get whether the product is extended size or not.
     *
     * @return bool
     */
    public function getIsExtendedSizeAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('EXTENDED SIZE', $identity);
    }

    /**
     * Get whether the product is coming soon or not.
     *
     * @return bool
     */
    public function getIsComingSoonAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('COMING SOON', $identity);
    }

    /**
     * Get whether the product is unisex or not.
     *
     * @return bool
     */
    public function getIsUnisexAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('UNISEX', $identity);
    }

    /**
     * Get whether the product is super large or not.
     *
     * @return bool
     */
    public function getIsSuperLargeAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('SUPERLARGE', $identity);
    }

    /**
     * Get whether the product is limit sale or not.
     *
     * @return bool
     */
    public function getIsLimitSaleAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('time_doptimal', $identity);
    }

    /**
     * Get whether the product is new or not.
     *
     * @return bool
     */
    public function getIsNewAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('new_product', $identity);
    }

    /**
     * Get whether the product is sale or not.
     *
     * @return bool
     */
    public function getIsSaleAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('concessional_rate', $identity);
    }

    /**
     * Get whether the product is revision or not.
     *
     * @return bool
     */
    public function getIsRevisionAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('revision', $identity);
    }

    /**
     * Get whether the product is stockout or not.
     *
     * @return bool
     */
    public function getIsStockoutAttribute()
    {
        return $this->stock === 'N';
    }

    /**
     * Get the product route url.
     *
     * @return string
     */
    public function getRouteUrlAttribute()
    {
        return route('hmall-products.show', ['hmallProduct' => $this->product_code]);
    }

    public function getLastAvailableAtAttribute()
    {
        if ($this->stockout_at) {
            return $this->stockout_at->subDay();
        }

        return $this->updated_at;
    }
}
