<?php

namespace App\Repositories;

use App\Enums\CategoryLevel;
use App\Enums\ProductTag;
use App\Models\HmallCategory;
use App\Models\HmallPriceHistory;
use App\Models\HmallProduct;
use App\Models\Product;
use App\Support\Keywords;
use App\Support\ProductSaveResult;
use Carbon\Carbon;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\FilterExpressionList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\OrderBy;
use Spatie\Analytics\Period;
use Throwable;

class HmallProductRepository extends Repository
{
    protected $model;

    private $hmallPriceHistory;

    private const CACHE_KEY_LIMITED_OFFER = 'hmall_product:limited_offer';

    private const CACHE_KEY_SALE = 'hmall_product:sale';

    private const CACHE_KEY_JAPAN_MOST_REVIEWED = 'hmall_product:japan_most_reviewed';

    private const CACHE_KEY_MOST_REVIEWED = 'hmall_product:most_reviewed';

    private const CACHE_KEY_TOP_WEARING = 'hmall_product:top_wearing';

    private const CACHE_KEY_NEW = 'hmall_product:new';

    private const CACHE_KEY_COMING_SOON = 'hmall_product:coming_soon';

    private const CACHE_KEY_MULTI_BUY = 'hmall_product:multi_buy';

    private const CACHE_KEY_ONLINE_SPECIAL = 'hmall_product:online_special';

    private const CACHE_KEY_MOST_VISITED = 'hmall_product:most_visited';

    private const CACHE_KEY_MOST_VISITED_RANKS = 'hmall_product:most_visited_ranks';

    private const CACHE_KEY_TOP_WEARING_RANKS = 'hmall_product:top_wearing_ranks';

    /** 分類頁與搜尋結果頁共用的每頁筆數 */
    public const PRODUCTS_PER_PAGE = 24;

    /** 關鍵字比對的欄位。LIKE '%詞%' 用不到索引，每多一欄就多掃一遍，不要隨手加長文字欄 */
    private const SEARCHABLE_COLUMNS = [
        'name',
        'product_name',
        'code',
        'product_code',
    ];

    private const SELECT_COLUMNS_FOR_LIST = [
        'hmall_products.id',
        'hmall_products.code',
        'hmall_products.brand',
        'hmall_products.product_code',
        'hmall_products.name',
        'hmall_products.min_price',
        'hmall_products.lowest_record_price',
        'hmall_products.highest_record_price',
        'hmall_products.lowest_record_price_count',
        'hmall_products.identity',
        'hmall_products.time_limited_begin',
        'hmall_products.time_limited_end',
        'hmall_products.score',
        'hmall_products.evaluation_count',
        'hmall_products.main_first_pic',
        'hmall_products.gender',
        'hmall_products.sex',
        'hmall_products.stock',
        'hmall_products.stockout_at',
        'hmall_products.updated_at',
    ];

    public function __construct(HmallProduct $model, HmallPriceHistory $hmallPriceHistory)
    {
        $this->model = $model;
        $this->hmallPriceHistory = $hmallPriceHistory;
    }

    public function getRelatedHmallProducts(HmallProduct $hmallProduct, $excludeItself = true)
    {
        $query = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(function ($query) use ($hmallProduct) {
                $query->where('code', $hmallProduct->code)
                    ->orWhere(function ($query) use ($hmallProduct) {
                        $query->where('name', 'like', "%{$hmallProduct->name}%")
                            ->where('gender', $hmallProduct->gender);
                    });
            });

        if ($excludeItself) {
            $query->where('id', '<>', $hmallProduct->id);
        }

        $query->orderByRaw('CASE WHEN `id` = ? THEN 0 ELSE 1 END', [$hmallProduct->id])
            ->orderByRaw('CASE WHEN `name` = ? THEN 0 ELSE 1 END', [$hmallProduct->name])
            ->orderBy(DB::raw('ISNULL(`stockout_at`)'), 'desc')
            ->orderByRaw('CHAR_LENGTH(`name`)')
            ->orderBy('min_price')
            ->orderBy('id', 'desc')
            ->take(6);

        return $query->get();
    }

    public function getStyles(HmallProduct $hmallProduct)
    {
        return $hmallProduct->styles;
    }

    public function getStyleHints(HmallProduct $hmallProduct, int $limit)
    {
        return $hmallProduct->styleHints()->orderBy('id', 'desc')->limit($limit)->get();
    }

    public function getStyleHintCount(HmallProduct $hmallProduct)
    {
        return $hmallProduct->styleHints()->count();
    }

    /**
     * 商品身上掛的大類與品項層分類。levelThree 是官方的錨點細分，不是會想點進去逛的分類。
     *
     * @return Collection<int, HmallCategory>
     */
    public function getCategoriesForProductPage(HmallProduct $hmallProduct): Collection
    {
        return $hmallProduct->categories()
            ->whereIn('level', [CategoryLevel::One->value, CategoryLevel::Two->value])
            ->orderBy('level', 'desc')
            ->orderBy('code')
            ->get();
    }

    public function getRelatedHmallProductsForProduct(Product $product)
    {
        $relatedId = substr($product->id, 0, 6);

        $hmallProduct = $this->model
            ->where('code', $relatedId)
            ->orderBy(DB::raw('ISNULL(`stockout_at`)'), 'desc')
            ->orderBy('min_price')
            ->orderBy('id', 'desc')
            ->first();

        if ($hmallProduct) {
            return $this->getRelatedHmallProducts($hmallProduct, false);
        }

        $similarHmallProducts = $this->getSimilarHmallProductsFromProduct($product);

        if ($similarHmallProducts->isNotEmpty()) {
            return $similarHmallProducts;
        }

        $sexTypes = ['男裝', '女裝', '童裝', '男童', '女童', '新生兒', '嬰幼兒'];
        $sexTypesPattern = implode('|', $sexTypes);

        preg_match("/({$sexTypesPattern})?(.*)/", $product->name, $matches);

        $relatedName = trim($matches[2]);
        $relatedSex = trim($matches[1]);

        return $this->getRelatedHmallProductsByName($relatedName, $relatedSex);
    }

    public function getSimilarHmallProductsFromProduct(Product $product)
    {
        $productRepository = app(ProductRepository::class);

        $relatedProducts = $productRepository->getRelatedProducts($product);
        $relatedProductIds = $relatedProducts->pluck('id')->all();

        return $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->whereIn('code', $relatedProductIds)
            ->orderBy(DB::raw('ISNULL(`stockout_at`)'), 'desc')
            ->orderBy('min_price')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getRelatedHmallProductsByName(string $name, string $sex = '')
    {
        return $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->where('product_name', 'like', "%{$name}%")
            ->where(function ($query) use ($sex) {
                $query->where('product_name', 'like', "%{$sex}%")
                    ->orWhere('gender', 'like', "%{$sex}%");
            })
            ->orderByRaw('CASE WHEN `name` = ? THEN 0 ELSE 1 END', [$name])
            ->orderBy(DB::raw('ISNULL(`stockout_at`)'), 'desc')
            ->orderByRaw('CHAR_LENGTH(`name`)')
            ->orderByRaw('CASE WHEN `gender` = "男裝" THEN 0 WHEN `gender` = "女裝" THEN 1 ELSE 2 END')
            ->orderBy('min_price')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getCommonlyStyledHmallProducts(HmallProduct $hmallProduct, int $limit = 6): Collection
    {
        $select = self::SELECT_COLUMNS_FOR_LIST;
        $select[] = DB::raw('count(*) as count');

        return HmallProduct::select($select)
            ->join('style_hint_items as items', 'hmall_products.code', '=', 'items.code')
            ->join('style_hint_items as related_items', 'items.style_hint_id', '=', 'related_items.style_hint_id')
            ->where('related_items.code', $hmallProduct->code)
            ->where('hmall_products.code', '<>', $hmallProduct->code)
            ->groupBy('hmall_products.id')
            ->orderByDesc('count')
            ->orderByDesc('hmall_products.evaluation_count')
            ->orderByDesc('hmall_products.id')
            ->take($limit)
            ->get();
    }

    public function getLimitedOfferHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_LIMITED_OFFER)) {
            $this->setLimitedOfferHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_LIMITED_OFFER);
    }

    public function getSaleHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_SALE)) {
            $this->setSaleHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_SALE);
    }

    public function getMostReviewedHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_MOST_REVIEWED)) {
            $this->setMostReviewedHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_MOST_REVIEWED);
    }

    public function getJapanMostReviewedHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_JAPAN_MOST_REVIEWED)) {
            $this->setJapanMostReviewedHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_JAPAN_MOST_REVIEWED);
    }

    public function getTopWearingHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_TOP_WEARING)) {
            $this->setTopWearingHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_TOP_WEARING);
    }

    public function getNewHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_NEW)) {
            $this->setNewHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_NEW);
    }

    public function getComingSoonHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_COMING_SOON)) {
            $this->setComingSoonHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_COMING_SOON);
    }

    public function getMultiBuyHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_MULTI_BUY)) {
            $this->setMultiBuyHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_MULTI_BUY);
    }

    public function getOnlineSpecialHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_ONLINE_SPECIAL)) {
            $this->setOnlineSpecialHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_ONLINE_SPECIAL);
    }

    public function getMostVisitedHmallProducts()
    {
        if (! Cache::has(self::CACHE_KEY_MOST_VISITED)) {
            $this->setMostVisitedHmallProductsCache();
        }

        return Cache::get(self::CACHE_KEY_MOST_VISITED);
    }

    /**
     * 以下清單的成員判準一律走 ProductTag（兩家品牌的官方標記對照都收在那裡），
     * 這裡只管排序與快取。標籤條件要包在自己的 where() 群組裡，OR 才不會漏到
     * 庫存條件外面。
     */
    public function setLimitedOfferHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(fn ($query) => ProductTag::LimitedOffer->applyTo($query))
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_LIMITED_OFFER, $hmallProducts);
    }

    public function setSaleHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(fn ($query) => ProductTag::Sale->applyTo($query))
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_SALE, $hmallProducts);
    }

    public function setMostReviewedHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where('evaluation_count', '>', function ($query) {
                $query->select('evaluation_count')
                    ->from('hmall_products')
                    ->where('stock', 'Y')
                    ->whereNull('stockout_at')
                    ->where('gender', '新生兒/嬰幼兒')
                    ->orderBy('evaluation_count', 'desc')
                    ->offset(4)
                    ->limit(1);
            })
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_MOST_REVIEWED, $hmallProducts);
    }

    public function setJapanMostReviewedHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->join('japan_products', 'hmall_products.code', '=', 'japan_products.l1id')
            ->where('rating_count', '>', function ($query) {
                $query->select('rating_count')
                    ->from('hmall_products')
                    ->join('japan_products', function (JoinClause $join) {
                        $join->on('hmall_products.code', '=', 'japan_products.l1id')
                            ->where('hmall_products.brand', '=', 'GU')
                            ->where('hmall_products.gender', '=', '新生兒/嬰幼兒');
                    })
                    ->orderBy('japan_products.rating_count', 'desc')
                    ->offset(1)
                    ->limit(1);
            })
            ->where('hmall_products.stock', 'Y')
            ->whereNull('hmall_products.stockout_at')
            ->orderBy('japan_products.rating_count', 'desc')
            ->orderBy('japan_products.rating_average', 'desc')
            ->orderBy('hmall_products.evaluation_count', 'desc')
            ->orderBy('hmall_products.score', 'desc')
            ->orderBy('hmall_products.created_at', 'desc')
            ->orderBy('hmall_products.id', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_JAPAN_MOST_REVIEWED, $hmallProducts);
    }

    public function setTopWearingHmallProductsCache()
    {
        $styleHintItems = DB::table('style_hint_items')
            ->select('style_hint_items.code')
            ->selectRaw('count(*) AS count')
            ->groupBy('style_hint_items.code')
            ->having('count', '>', 100)
            ->orderBy('count', 'desc');

        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->addSelect('count')
            ->leftJoinSub($styleHintItems, 'style_hint_items', function ($join) {
                $join->on('hmall_products.code', '=', 'style_hint_items.code');
            })
            ->whereNotNull('count')
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderBy('count', 'desc')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $ranks = $hmallProducts->pluck('id')->mapWithKeys(function ($id, $index) {
            return [$id => $index + 1];
        });

        Cache::forever(self::CACHE_KEY_TOP_WEARING, $hmallProducts);
        Cache::forever(self::CACHE_KEY_TOP_WEARING_RANKS, $ranks);
    }

    public function setNewHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(fn ($query) => ProductTag::NewArrival->applyTo($query))
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_NEW, $hmallProducts);
    }

    public function setComingSoonHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(fn ($query) => ProductTag::ComingSoon->applyTo($query))
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_COMING_SOON, $hmallProducts);
    }

    public function setMultiBuyHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(fn ($query) => ProductTag::MultiBuy->applyTo($query))
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_MULTI_BUY, $hmallProducts);
    }

    public function setOnlineSpecialHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(fn ($query) => ProductTag::OnlineSpecial->applyTo($query))
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_ONLINE_SPECIAL, $hmallProducts);
    }

    public function setMostVisitedHmallProductsCache()
    {
        try {
            $hmallProducts = $this->getMostVisitedProducts();

            $ranks = $hmallProducts->pluck('id')->mapWithKeys(function ($id, $index) {
                return [$id => $index + 1];
            });

            Cache::forever(self::CACHE_KEY_MOST_VISITED, $hmallProducts);
            Cache::forever(self::CACHE_KEY_MOST_VISITED_RANKS, $ranks);
        } catch (Throwable $e) {
            Log::error('Failed to get most visited products from GA', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            Cache::forever(self::CACHE_KEY_MOST_VISITED, collect([]));
        }
    }

    /**
     * 依品牌與商品編號批次取商品，順序照傳入的順序（收藏頁要維持收藏順序）。
     *
     * 兩家共用同一組編號空間，同一個 product_code 在兩家可能是不同商品，
     * 所以一定要連品牌一起配對。
     *
     * @param  array<int, array{brand: string, code: string}>  $items
     * @return Collection<int, HmallProduct>
     */
    public function getByBrandAndProductCodes(array $items): Collection
    {
        $products = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->whereIn('product_code', array_column($items, 'code'))
            ->get()
            ->keyBy(fn (HmallProduct $product) => $product->brand.':'.$product->product_code);

        return collect($items)
            ->map(fn (array $item) => $products->get($item['brand'].':'.$item['code']))
            ->filter()
            ->values();
    }

    /**
     * 取出某個分類底下還買得到的商品，照官方在該分類內的權重排（跟官網順序一致）。
     *
     * 不走清單頁那套預熱快取：分類多、一個大類上千件，還要疊標籤、關鍵字與分頁。
     * 標籤與關鍵字都要在查詢層篩，分頁後才篩會漏商品、頁數也會錯。
     *
     * @param  array<int, ProductTag>  $tags
     */
    public function getProductsByCategoryId(
        int $categoryId,
        array $tags = [],
        int $perPage = self::PRODUCTS_PER_PAGE,
        ?string $q = null
    ): LengthAwarePaginator {
        $query = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->join(
                'hmall_category_hmall_product as category_pivot',
                'category_pivot.hmall_product_id',
                '=',
                'hmall_products.id'
            )
            ->where('category_pivot.hmall_category_id', $categoryId)
            ->where('hmall_products.stock', 'Y')
            ->whereNull('hmall_products.stockout_at');

        if (! empty($tags)) {
            $query->where(function ($group) use ($tags) {
                foreach ($tags as $tag) {
                    $tag->applyTo($group);
                }
            });
        }

        if (filled($q)) {
            $this->applyKeywordFilterForCategory($query, $q);
        }

        return $query
            ->orderBy('category_pivot.sort')
            ->orderBy('hmall_products.code')
            // sort 與 code 都常重複，少了唯一鍵翻頁會重複或漏掉商品
            ->orderBy('hmall_products.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * 分類頁的關鍵字篩選：每個詞都要命中品名或編號。
     *
     * 刻意不像 searchByKeywords() 比對分類名稱：已經在這個分類裡了。
     */
    private function applyKeywordFilterForCategory($query, string $q): void
    {
        foreach (Keywords::split($q) as $keyword) {
            $pattern = '%'.$this->escapeLikeWildcards($keyword).'%';

            $query->where(function ($subQuery) use ($pattern) {
                $subQuery->where('hmall_products.name', 'like', $pattern)
                    ->orWhere('hmall_products.code', 'like', $pattern)
                    ->orWhere('hmall_products.product_code', 'like', $pattern);
            });
        }
    }

    /**
     * 用數字查詢找商品：code 精準符合，或者 name 裡以獨立數字段落出現這組號碼。
     *
     * UNIQLO 常把多個貨號（顏色、款式）共用同一個商品頁，商品頁的 code 只會是
     * 其中一個，其餘號碼只出現在 name 裡（例如「AIRism 圓領T恤(短袖) 474238 /
     * 482514 / 474236」）。REGEXP 前後各夾一個「非數字或字串頭尾」，避免 482514
     * 誤中 4825140 這種只是前綴相同的號碼。
     *
     * 呼叫端要保證 $query 只含 ASCII 數字（它會被拼進正規表示式）。
     */
    public function findHmallProductsByCodeOrSharedNumber(string $query): Collection
    {
        $pattern = '(^|[^0-9])'.$query.'([^0-9]|$)';

        return $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where('code', $query)
            ->orWhere(function ($subQuery) use ($pattern) {
                $subQuery->whereRaw('name REGEXP ?', [$pattern]);
            })
            ->orderByRaw('code = ? DESC', [$query])
            ->orderBy('min_price')
            ->orderBy('id')
            ->get();
    }

    /**
     * 依關鍵字搜尋商品，每個詞都要命中品名、編號或商品掛的分類名稱
     * （「外套」這種詞只出現在分類上）。
     *
     * @param  array<int, string>  $keywords
     */
    public function searchByKeywords(array $keywords, int $perPage = self::PRODUCTS_PER_PAGE): LengthAwarePaginator
    {
        $query = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct');

        // 少了 where 就是整張表
        if (empty($keywords)) {
            $query->whereRaw('1 = 0');
        }

        foreach ($keywords as $keyword) {
            $pattern = '%'.$this->escapeLikeWildcards($keyword).'%';

            $query->where(function ($subQuery) use ($pattern) {
                foreach (self::SEARCHABLE_COLUMNS as $column) {
                    $subQuery->orWhere($column, 'like', $pattern);
                }

                // 刻意用不引用外層的子查詢，MySQL 才會一次算完符合的 id；
                // 改成 whereExists 會變成每筆商品各跑一次，慢十倍以上
                $subQuery->orWhereIn('hmall_products.id', function ($ids) use ($pattern) {
                    $ids->select('search_pivot.hmall_product_id')
                        ->from('hmall_category_hmall_product as search_pivot')
                        ->join(
                            'hmall_categories',
                            'hmall_categories.id',
                            '=',
                            'search_pivot.hmall_category_id'
                        )
                        ->where('hmall_categories.name', 'like', $pattern);
                });
            });
        }

        return $query
            ->orderByRaw('stockout_at IS NOT NULL')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('code')
            ->orderBy('hmall_products.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** 讓使用者輸入的 % 與 _ 當一般文字比對；反斜線要最先跳脫 */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * 寫入一頁商品，回傳哪幾件沒寫進去（缺貨判定要排除它們）。
     *
     * 單一商品失敗不中斷整頁。拿不到商品編號的失敗另外計數：排除不了，
     * 呼叫端只能整輪不做缺貨判定。
     */
    public function saveProductsFromV3($products, $brand = 'UNIQLO'): ProductSaveResult
    {
        // 分類主檔一頁只寫一次，回傳的 code 對 id 對照表給下面掛關聯用。
        try {
            $categoryIds = $this->saveCategoriesFromV3($products, $brand);
        } catch (Throwable $e) {
            // 分類寫不進去不能拖累價格：改用空的對照表照寫商品，syncCategories
            // 遇到空對照表會跳過，既有的分類關聯原封不動。例外也不能往外丟，
            // 否則會穿出呼叫端的 retry、拿資料庫的問題去重打官網。
            Log::error('saveCategoriesFromV3 error - saving products without touching their categories', [
                'brand' => $brand,
            ]);

            report($e);

            $categoryIds = collect();
        }

        $failedProductCodes = [];
        $unidentifiedFailureCount = 0;

        collect($products)->each(function ($product) use (
            $brand,
            $categoryIds,
            &$failedProductCodes,
            &$unidentifiedFailureCount
        ) {
            try {
                /** @var HmallProduct $model */
                $model = $this->model->firstOrNew([
                    'brand' => $brand,
                    'product_code' => $product->productCode,
                ]);

                // 先計算相關資訊後再更新 model
                $lowestRecordPriceCount = $this->getLowestRecordPriceCount($model, $product->minPrice);
                $isChangedThePrice = $this->isChangedThePrice($model, $product);

                $model->code = $product->code ?? null;
                $model->brand = $brand ?? null;
                $model->product_code = $product->productCode ?? null;
                $model->oms_product_code = $product->omsProductCode ?? null;
                $model->name = $product->name ?? null;
                $model->product_name = $product->productName ?? null;
                $model->prices = isset($product->prices) ? json_encode($product->prices) : null;
                $model->min_price = $product->minPrice ?? null;
                $model->max_price = $product->maxPrice ?? null;
                $model->lowest_record_price = $this->getLowestRecordPrice($model, $product->minPrice ?? null);
                $model->highest_record_price = $this->getHighestRecordPrice($model, $product->maxPrice ?? null);
                $model->lowest_record_price_count = $lowestRecordPriceCount ?? null;
                $model->origin_price = $product->originPrice ?? null;
                $model->price_color = $product->priceColor ?? null;
                $model->identity = isset($product->identity) ? json_encode($product->identity) : null;
                $model->label = $product->label ?? null;
                $model->time_limited_begin = $this->getCarbonOrNull($product->timeLimitedBegin ?? null);
                $model->time_limited_end = $this->getCarbonOrNull($product->timeLimitedEnd ?? null);
                $model->score = $product->score ?? null;
                $model->size_score = $product->sizeScore ?? null;
                $model->evaluation_count = $product->evaluationCount ?? null;
                $model->sales = $product->sales ?? null;
                $model->new = $product->new ?? null;
                $model->season = $product->season ?? null;
                $model->style_text = isset($product->styleText) ? json_encode($product->styleText) : null;
                $model->color_nums = isset($product->colorNums) ? json_encode($product->colorNums) : null;
                $model->color_pic = isset($product->colorPic) ? json_encode($product->colorPic) : null;
                $model->chip_pic = isset($product->chipPic) ? json_encode($product->chipPic) : null;
                $model->main_first_pic = $product->mainPic ?? null;
                $model->size = isset($product->size) ? json_encode($product->size) : null;
                $model->min_size = $product->minSize ?? null;
                $model->max_size = $product->maxSize ?? null;
                $model->gender = $product->gender ?? null;
                $model->sex = $product->sex ?? null;
                $model->material = $product->material ?? null;

                $model->stockout_at = $this->getStockoutAt($model, $product);
                $model->stock = $product->stock ?? null;

                // 價格與歷史要一起回滾：新價格先寫進去而歷史沒寫的話，下次抓到同價
                // 會被判「沒變」，價格走勢就永久缺一筆
                DB::transaction(function () use ($model, $isChangedThePrice) {
                    $model->save();

                    if (! $isChangedThePrice) {
                        return;
                    }

                    $hmallPriceHistory = new HmallPriceHistory;
                    $hmallPriceHistory->min_price = $model->min_price;
                    $hmallPriceHistory->max_price = $model->max_price;
                    $model->hmallPriceHistories()->save($hmallPriceHistory);
                });
            } catch (Throwable $e) {
                $productCode = $product->productCode ?? null;

                // 官方格式跑掉時 productCode 可能缺、空、甚至是陣列
                if (is_string($productCode) && $productCode !== '') {
                    $failedProductCodes[] = $productCode;
                } else {
                    $unidentifiedFailureCount++;
                }

                Log::error('saveProductsFromHmall error', [
                    'brand' => $brand,
                    'product_code' => is_string($productCode) ? $productCode : null,
                ]);

                report($e);

                return;
            }

            // 分類是附屬資訊，同步失敗只留紀錄、既有關聯不動，不能讓價格跟著寫不進去
            try {
                DB::transaction(fn () => $this->syncCategories($model, $product, $categoryIds));
            } catch (Throwable $e) {
                Log::error('syncCategories error - keeping existing category links', [
                    'brand' => $brand,
                    'product_code' => $model->product_code,
                ]);

                report($e);
            }
        });

        return new ProductSaveResult(
            array_values(array_unique($failedProductCodes)),
            $unidentifiedFailureCount
        );
    }

    /**
     * 把這一輪沒被更新到的商品標成下架。$excludedProductCodes 是寫入失敗、
     * 但來源其實還在的商品。
     *
     * @param  array<int, string>  $excludedProductCodes
     */
    public function setStockoutHmallProducts($brand = 'UNIQLO', $updatedIsBefore = null, array $excludedProductCodes = [])
    {
        if (is_null($updatedIsBefore)) {
            $updatedIsBefore = today();
        }

        $query = $this->model
            ->whereNull('stockout_at')
            ->where('brand', $brand)
            ->where('updated_at', '<', $updatedIsBefore);

        if ($excludedProductCodes !== []) {
            // product_code 允許 NULL，而 NULL NOT IN (...) 永遠不成立，
            // 只寫 whereNotIn 會把 NULL 的商品一起排除掉
            $query->where(function ($query) use ($excludedProductCodes) {
                $query->whereNotIn('product_code', $excludedProductCodes)
                    ->orWhereNull('product_code');
            });
        }

        $query->update([
            'stockout_at' => now(),
            'updated_at' => DB::raw('updated_at'),
        ]);
    }

    public function updateProductDescriptionsFromV3(
        HmallProduct $hmallProduct,
        string $instruction,
        string $sizeChart,
        bool $updateTimestamps = false
    ) {
        $hmallProduct->instruction = $instruction;
        $hmallProduct->size_chart = $sizeChart;

        $hmallProduct->timestamps = $updateTimestamps;
        $hmallProduct->save();
    }

    public function getAllProductsForSitemap()
    {
        return $this->model
            ->select(['id', 'brand', 'product_code', 'updated_at'])
            ->lazyByIdDesc();
    }

    public function getIdsFromCodes(array $codes)
    {
        return $this->model
            ->select(['id'])
            ->whereIn('code', $codes)
            ->get();
    }

    private function getLowestRecordPriceCount($model, $newMinPrice): int
    {
        // 新商品或出現新歷史低價
        if (is_null($model->lowest_record_price) || $newMinPrice < $model->lowest_record_price) {
            return 1;
        }

        // 再度出現與歷史低價相同的新檔期
        if (
            intval($newMinPrice) === intval($model->lowest_record_price)
            && intval($newMinPrice) !== intval($model->min_price)
        ) {
            return $model->lowest_record_price_count + 1;
        }

        // 與前次抓取相同檔期，或是比歷史低價還高
        return $model->lowest_record_price_count;
    }

    private function getLowestRecordPrice($model, $newMinPrice)
    {
        $lowestRecordPrice = $model->lowest_record_price;

        if (empty($lowestRecordPrice)) {
            return $newMinPrice;
        }

        return min($lowestRecordPrice, $newMinPrice);
    }

    private function getHighestRecordPrice($model, $newMaxPrice)
    {
        $highestRecordPrice = $model->highest_record_price;

        if (empty($highestRecordPrice)) {
            return $newMaxPrice;
        }

        return max($highestRecordPrice, $newMaxPrice);
    }

    /**
     * 從整批商品的回傳裡取出分類主檔並寫入，回傳 code 對 id 的對照表。
     *
     * 官方每筆商品都帶完整的四層分類物件，所以主檔不另外抓。
     *
     * @return Collection<string, int>
     */
    private function saveCategoriesFromV3($products, string $brand): Collection
    {
        $categories = collect($products)
            ->flatMap(fn ($product) => $this->extractCategories($product, $brand))
            ->unique('code')
            ->values();

        if ($categories->isEmpty()) {
            return collect();
        }

        HmallCategory::upsert($categories->all(), ['brand', 'code'], ['name', 'parent_code', 'level']);

        return HmallCategory::where('brand', $brand)
            ->whereIn('code', $categories->pluck('code'))
            ->pluck('id', 'code');
    }

    /**
     * 把商品的四層分類陣列攤平成主檔資料列。
     *
     * 層級由它出現在哪個陣列決定：官方的同一個 code 不會跨層出現。
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractCategories($product, string $brand): array
    {
        $levels = [
            [CategoryLevel::Top, $product->topCategories ?? []],
            [CategoryLevel::One, $product->levelOne ?? []],
            [CategoryLevel::Two, $product->levelTwo ?? []],
            [CategoryLevel::Three, $product->levelThree ?? []],
        ];

        $categories = [];

        foreach ($levels as [$level, $items]) {
            foreach ($items as $item) {
                if (empty($item->code)) {
                    continue;
                }

                $categories[] = [
                    'brand' => $brand,
                    'code' => $item->code,
                    'name' => $item->name ?? $item->code,
                    'parent_code' => $item->parentCode ?? null,
                    // upsert 走 query builder、不套 model 的 cast，這裡要給原始值
                    'level' => $level->value,
                ];
            }
        }

        return $categories;
    }

    /**
     * 更新商品掛在哪些分類底下。
     *
     * 分類歸屬以四層陣列為準，categorySortList 只提供該分類內的排序權重。
     * 這次回傳沒有分類、或分類主檔沒寫進去時不動既有關聯：比較可能是缺漏，
     * 不是商品真的被移出所有分類。
     */
    private function syncCategories(HmallProduct $model, $product, Collection $categoryIds): void
    {
        $codes = collect($this->extractCategories($product, $model->brand))
            ->pluck('code')
            ->unique()
            ->filter(fn (string $code) => $categoryIds->has($code));

        if ($codes->isEmpty()) {
            return;
        }

        $sorts = collect($product->categorySortList ?? [])
            ->filter(fn ($item) => ! empty($item->code))
            ->mapWithKeys(fn ($item) => [$item->code => $item->sort ?? null]);

        $model->categories()->sync(
            $codes->mapWithKeys(fn (string $code) => [
                $categoryIds->get($code) => ['sort' => $sorts->get($code)],
            ])->all()
        );
    }

    private function getCarbonOrNull($unixTimestampInMilliseconds)
    {
        if (empty($unixTimestampInMilliseconds)) {
            return null;
        }

        return Carbon::createFromTimestampMs($unixTimestampInMilliseconds);
    }

    private function getStockoutAt($model, $product)
    {
        // 有庫存，移除售罄時間
        if ($product->stock === 'Y') {
            return null;
        }

        // 這次才無庫存
        if ($product->stock === 'N' && empty($model->stockout_at)) {
            return now();
        }

        // 先前就無庫存
        return $model->stockout_at;
    }

    private function isChangedThePrice($model, $product)
    {
        if (is_null($model->min_price) || is_null($model->max_price)) {
            return true;
        }

        return intval($model->min_price) !== intval($product->minPrice)
            || intval($model->max_price) !== intval($product->maxPrice);
    }

    private function fetchMostVisitedProducts(int $days, int $maxResults = 20, int $offset = 0): Collection
    {
        return Analytics::get(
            period: Period::days($days),
            metrics: ['screenPageViews'],
            dimensions: ['fullPageUrl'],
            maxResults: $maxResults,
            orderBy: [
                OrderBy::metric('screenPageViews', true),
            ],
            offset: $offset,
            dimensionFilter: $this->getDimensionFilter(),
        )->mapWithKeys(function ($item) {
            return [$item['fullPageUrl'] => $item['screenPageViews']];
        });
    }

    private function getDimensionFilter(): FilterExpression
    {
        $filters = collect([
            '/hmall-products/',
            '/gu-products/',
        ])->map(function ($path) {
            return new FilterExpression([
                'filter' => new Filter([
                    'field_name' => 'pagePath',
                    'string_filter' => new StringFilter([
                        'match_type' => MatchType::BEGINS_WITH,
                        'value' => $path,
                    ]),
                ]),
            ]);
        });

        return new FilterExpression([
            'or_group' => new FilterExpressionList([
                'expressions' => $filters->toArray(),
            ]),
        ]);
    }

    private function getMostVisitedProducts(): Collection
    {
        $recentAnalyticsData = $this->fetchMostVisitedProducts(2, 100);
        $weeklyAnalyticsData = $this->fetchMostVisitedProducts(7, 100);

        $rank = $this->calculateProductRanking($recentAnalyticsData, $weeklyAnalyticsData);

        return $this->fetchRankedProducts($rank);
    }

    private function calculateProductRanking(Collection $recentData, Collection $weeklyData): Collection
    {
        return $recentData->map(function ($recentViews, $fullPageUrl) use ($weeklyData) {
            $recentAverageViews = $recentViews / 2;
            $weeklyAverageViews = ($weeklyData[$fullPageUrl] ?? 0) / 7;
            $weightedViews = ($recentAverageViews * 0.7) + ($weeklyAverageViews * 0.3);

            return [
                'brand' => $this->getBrandFromUrl($fullPageUrl),
                'productCode' => $this->getProductCodeFromUrl($fullPageUrl),
                'weightedViews' => $weightedViews,
            ];
        })
            // 編號過不了白名單的整筆丟掉：GA 收得到任何人亂打的網址，
            // 這些片段本來就對不到商品，沒有理由讓它往下走。
            ->filter(fn ($item) => $item['productCode'] !== null)
            ->unique(fn ($item) => "{$item['brand']}_{$item['productCode']}")
            ->sortByDesc('weightedViews');
    }

    private function getBrandFromUrl(string $fullPageUrl): string
    {
        return str_contains($fullPageUrl, '/gu-products/') ? 'GU' : 'UNIQLO';
    }

    /**
     * 從 GA 的 fullPageUrl 取商品編號。
     *
     * 這個值是外部可以自己灌進來的：任何人反覆打 /hmall-products/<任意字串>，
     * 就算回 404，錯誤頁一樣 include 了 GA 的 script，那個路徑還是會被記成一筆
     * pagePath，再被 getDimensionFilter() 的 BEGINS_WITH 收進這份排行。所以一律
     * 先過白名單，只認英數、底線與連字號；不合格式的回 null，讓呼叫端整筆丟掉，
     * 不要讓它有機會進到任何一段 SQL。
     */
    private function getProductCodeFromUrl(string $fullPageUrl): ?string
    {
        $productCode = explode('/', $fullPageUrl)[2] ?? '';

        return preg_match('/^[A-Za-z0-9_-]+$/', $productCode) === 1 ? $productCode : null;
    }

    private function fetchRankedProducts(Collection $rank): Collection
    {
        if ($rank->isEmpty()) {
            return collect([]);
        }

        $productIdentifiers = $rank
            ->map(fn ($item) => "{$item['brand']}_{$item['productCode']}")
            ->values()
            ->all();

        // ORDER BY 的名次清單跟上面的 whereIn 一樣要走 binding。以前這裡是把
        // 商品編號加引號直接拼進字串，而編號來自 GA 的網址片段（外部值），
        // 等於把 ORDER BY 開放給外部寫入。編號那端已經有白名單，這端用佔位符，
        // 兩層都守住。
        $orderByField = sprintf(
            'FIELD(CONCAT(brand, \'_\', product_code), %s)',
            implode(', ', array_fill(0, count($productIdentifiers), '?'))
        );

        return $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->whereIn(DB::raw("CONCAT(brand, '_', product_code)"), $productIdentifiers)
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw($orderByField, $productIdentifiers)
            ->get();
    }
}
