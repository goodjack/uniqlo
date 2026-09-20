<?php

namespace App\Models;

use App\Enums\ProductTag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HmallProduct extends Model
{
    use HasFactory;

    protected $fillable = ['product_code'];

    private const CACHE_KEY_MOST_VISITED = 'hmall_product:most_visited';

    private const CACHE_KEY_TOP_WEARING = 'hmall_product:top_wearing';

    private const CACHE_KEY_MOST_VISITED_RANKS = 'hmall_product:most_visited_ranks';

    private const CACHE_KEY_TOP_WEARING_RANKS = 'hmall_product:top_wearing_ranks';

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

    public function categories()
    {
        return $this->belongsToMany(
            HmallCategory::class,
            'hmall_category_hmall_product',
            'hmall_product_id',
            'hmall_category_id'
        )->withPivot('sort');
    }

    public function styles()
    {
        return $this->belongsToMany(Style::class)->orderByDesc('created_at');
    }

    public function styleHints()
    {
        return $this->belongsToMany(
            StyleHint::class,
            'style_hint_items',
            'code',
            'style_hint_id',
            'code'
        )->orderByDesc('id');
    }

    public function japanProduct()
    {
        return $this->hasOne(JapanProduct::class, 'l1Id', 'code');
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
        return ProductTag::OnlineSpecial->matches($this);
    }

    /**
     * Get whether the product is multi buy or not.
     *
     * @return bool
     */
    public function getIsMultiBuyAttribute()
    {
        return ProductTag::MultiBuy->matches($this);
    }

    /**
     * Get whether the product is ec only or not.
     *
     * ProductTag 沒有對應的 case（ECONLY 在那邊只是「網路獨家」的其中一個代碼，
     * 不是自己一個標籤），所以這個保留原本的實作。
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
        return ProductTag::ComingSoon->matches($this);
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
        return ProductTag::LimitedOffer->matches($this);
    }

    /**
     * Get whether the product is app offer or not.
     *
     * ProductTag 沒有對應的 case（APP 已經從期間限定拿掉，它講的是通路不是檔期），
     * 所以這個保留原本的實作。
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
        return ProductTag::NewArrival->matches($this);
    }

    /**
     * Get whether the product is sale or not.
     *
     * @return bool
     */
    public function getIsSaleAttribute()
    {
        return ProductTag::Sale->matches($this);
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

    /**
     * 目前的價格就是有記錄以來的最低。
     *
     * 這個刻意不委派給 ProductTag::LowestPrice：那個標籤的 matches() 讀的就是
     * 這個 accessor，反過來委派會無限遞迴。
     *
     * 這是給篩選用的，跟卡片上那個「歷史新低價」標籤不同：那個標籤刻意排除官方
     * 標為特價的商品，否則特價期間整頁會同時掛兩個標籤、互相干擾。但使用者想在
     * 特價清單裡找「這波真的是史上最低」的商品時，要的正是被那個條件擋掉的東西。
     */
    public function getIsAtLowestPriceAttribute(): bool
    {
        return $this->min_price !== null
            && $this->lowest_record_price !== null
            && $this->min_price === $this->lowest_record_price
            && $this->lowest_record_price < $this->highest_record_price;
    }

    public function getIsNewHistoricalLowAttribute(): bool
    {
        return $this->lowest_record_price_count === 1
            && $this->min_price === $this->lowest_record_price
            && $this->lowest_record_price < $this->highest_record_price
            && ! $this->is_sale;
    }

    public function getMostVisitedRankAttribute(): ?int
    {
        return Cache::get(self::CACHE_KEY_MOST_VISITED_RANKS)[$this->id] ?? null;
    }

    public function getTopWearingRankAttribute(): ?int
    {
        return Cache::get(self::CACHE_KEY_TOP_WEARING_RANKS)[$this->id] ?? null;
    }
}
