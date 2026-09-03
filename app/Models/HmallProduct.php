<?php

namespace App\Models;

use App\Enums\CategoryLevel;
use App\Enums\ProductTag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
     * 商品的性別對應到哪些頂層分類。
     *
     * 兩家的頂層 code 各成一套（UNIQLO 是 all_men、GU 是 men_all），名稱也不同
     * （男裝／MEN），所以用 code 對照而不是比名稱。值是本機資料庫 2026-09 快照裡
     * 實際存在的頂層分類；對不到的性別（包含空字串）就不套性別條件。
     */
    private const GENDER_TOP_CATEGORIES = [
        '男裝' => ['all_men', 'men_all'],
        '女裝' => ['all_women', 'women_all', 'specialsize_w'],
        '童裝' => ['all_kids', 'kids_all'],
        '男童' => ['all_kids', 'kids_all'],
        '女童' => ['all_kids', 'kids_all'],
        '新生兒/嬰幼兒' => ['all_baby'],
    ];

    /**
     * 分類樹最深四層，往上回溯的次數上限。資料是爬蟲寫的，parent_code 萬一
     * 兜成環，沒有上限就會轉不出來。
     */
    private const MAX_CATEGORY_DEPTH = 5;

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

    /**
     * 這件商品的主分類，也就是麵包屑要走的那一條路徑。
     *
     * 一件商品平均掛十幾個分類，官方沒有給主分類，所以規則是自己定的：
     *
     * 一、取品項層（levelTwo），它比大類具體、對「找同類商品」最有用。
     * 二、只認性別跟商品自己相符的那幾棵樹。少了這一條會選到「熱門推薦」——
     *     那是促銷用的樹，官方給它的 sort 又常常最小。實測 u0000000055090
     *     （女裝 HEATTECH）掛的四個品項裡 sort 最小的就是「熱門推薦 › 週週
     *     新品一覽 › 女裝 新品一覽」，那不是使用者想回去逛的地方。
     * 三、同一層有多個就取官方 sort 最小的。sort 是官方回傳的排序字串
     *     （008004001008004009 這種），照字串比，不要轉成數字。
     *
     * 找不到符合性別的就退一步：先放寬到大類（levelOne），再放寬成不管性別。
     * 掛得到分類就給得出麵包屑，比整條退成「首頁 › 商品」有用。
     */
    public function primaryCategory(): ?HmallCategory
    {
        $categories = $this->categories;
        // 商品身上掛的是完整的四層，父分類查得到，不必再回資料庫
        $byCode = $categories->keyBy('code');
        $topCodes = self::GENDER_TOP_CATEGORIES[$this->gender] ?? null;

        foreach ([true, false] as $matchGender) {
            foreach ([CategoryLevel::Two, CategoryLevel::One] as $level) {
                $found = $categories
                    ->filter(fn (HmallCategory $category) => $category->level === $level)
                    ->filter(fn (HmallCategory $category) => ! $matchGender
                        || $topCodes === null
                        || in_array($this->topCodeOf($category, $byCode), $topCodes, true))
                    // SORT_STRING 不能省：PHP 的 <=> 對兩個數字字串是照數值比，
                    // 而 sort 是長度不一的官方排序字串（008004001008004009 對
                    // 014001999），照數值比會挑錯——實測就是這樣選到後面那個。
                    ->sortBy(fn (HmallCategory $category) => (string) $category->pivot->sort, SORT_STRING)
                    ->first();

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * 從一個分類往上回溯到頂層，回傳頂層的 code。中途斷掉就回 null。
     *
     * @param  Collection<string, HmallCategory>  $byCode
     */
    private function topCodeOf(HmallCategory $category, Collection $byCode): ?string
    {
        $current = $category;

        for ($depth = 0; $depth < self::MAX_CATEGORY_DEPTH; $depth++) {
            if ($current->parent_code === null) {
                return $current->code;
            }

            $current = $byCode->get($current->parent_code);

            if ($current === null) {
                return null;
            }
        }

        return null;
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
