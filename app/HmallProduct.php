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

    public function styles()
    {
        return $this->belongsToMany(Style::class);
    }

    public function styleHints()
    {
        return $this->belongsToMany(
            StyleHint::class,
            'style_hint_items',
            'code',
            'style_hint_id',
            'code'
        );
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
        // UNIQLO: ONLINE SPECIAL
        // GU: ECONLY

        $identity = json_decode($this->identity);

        return in_array('ONLINE SPECIAL', $identity) || in_array('ECONLY', $identity);
    }

    /**
     * Get whether the product is multi buy or not.
     *
     * @return bool
     */
    public function getIsMultiBuyAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('multi_buy', $identity) || in_array('SET', $identity);
    }

    /**
     * Get whether the product is ec only or not.
     *
     * @return bool
     */
    public function getIsEcOnlyAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('ECONLY', $identity);
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
        // UNIQLO: COMING SOON
        // GU: COMING

        $identity = json_decode($this->identity);

        return in_array('COMING SOON', $identity) || in_array('COMING', $identity);
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
        // UNIQLO: 旗艦店款

        $identity = json_decode($this->identity);

        return in_array('SUPERLARGE', $identity);
    }

    /**
     * Get whether the product is EC Big or not.
     *
     * @return bool
     */
    public function getIsEcBigAttribute()
    {
        // GU: 大型店商品

        $identity = json_decode($this->identity);

        return in_array('ECBIG', $identity);
    }

    /**
     * Get whether the product is EC Selected or not.
     *
     * @return bool
     */
    public function getIsEcSelectedAttribute()
    {
        // GU: 特定店商品

        $identity = json_decode($this->identity);

        return in_array('ECSELECTED', $identity);
    }

    /**
     * Get whether the product is limited offer or not.
     *
     * @return bool
     */
    public function getIsLimitedOfferAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('time_doptimal', $identity);
    }

    /**
     * Get whether the product is app offer or not.
     *
     * @return bool
     */
    public function getIsAppOfferAttribute()
    {
        $identity = json_decode($this->identity);

        return in_array('APP', $identity);
    }

    /**
     * Get the end date of the limited offer.
     *
     * @return bool
     */
    public function getLimitedOfferEndDateAttribute()
    {
        $now = now();

        $condition = $now <= $this->time_limited_end && $now >= $this->time_limited_begin;

        if (! $condition) {
            return null;
        }

        return $this->time_limited_end->startOfDay();
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
        return $this->stock === 'N' || isset($this->stockout_at);
    }

    /**
     * Get the product route url.
     *
     * @return string
     */
    public function getRouteUrlAttribute()
    {
        if ($this->brand === 'GU') {
            return route('gu-hmall-products.show', ['gu_product_code' => $this->product_code]);
        }

        return route('uniqlo-hmall-products.show', ['uniqlo_product_code' => $this->product_code]);
    }

    public function getLastAvailableAtAttribute()
    {
        if ($this->stockout_at) {
            return $this->stockout_at->subDay();
        }

        return $this->updated_at;
    }

    public function getShortProductCodeAttribute()
    {
        $shortCodeNumber = substr($this->product_code, -7);

        return "u{$shortCodeNumber}";
    }
}
