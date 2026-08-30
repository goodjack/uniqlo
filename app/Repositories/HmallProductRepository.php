<?php

namespace App\Repositories;

use App\Enums\CategoryLevel;
use App\Enums\ProductTag;
use App\Models\HmallCategory;
use App\Models\HmallPriceHistory;
use App\Models\HmallProduct;
use App\Models\Product;
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

    /**
     * 關鍵字比對的欄位。
     *
     * 刻意不含 product_name 以外的長文字欄位：LIKE '%詞%' 一律全表掃描，
     * 每多一欄就多掃一遍。
     */
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

    public function setLimitedOfferHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('time_limited_begin', '<=', now())
                        ->where('time_limited_end', '>=', now());
                })->orWhere('identity', 'like', '%time_doptimal%');
            })
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_LIMITED_OFFER, $hmallProducts);
    }

    public function setSaleHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where('identity', 'like', '%concessional_rate%')
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
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
            ->where('identity', 'like', '%new_product%')
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_NEW, $hmallProducts);
    }

    public function setComingSoonHmallProductsCache()
    {
        // UNIQLO: COMING SOON
        // GU: COMING

        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where('identity', 'like', '%COMING%')
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_COMING_SOON, $hmallProducts);
    }

    public function setMultiBuyHmallProductsCache()
    {
        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(function ($query) {
                $query->where('identity', 'like', '%multi_buy%')
                    ->orWhere('identity', 'like', '%SET%');
            })
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_MULTI_BUY, $hmallProducts);
    }

    public function setOnlineSpecialHmallProductsCache()
    {
        // UNIQLO: ONLINE SPECIAL
        // GU: ECONLY

        $hmallProducts = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->where(function ($query) {
                $query->where('identity', 'like', '%ONLINE SPECIAL%')
                    ->orWhere('identity', 'like', '%ECONLY%');
            })
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw('min_price/highest_record_price')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc')
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
     * 依品牌與商品編號批次取商品，用於收藏清單。
     *
     * 一定要連品牌一起指定：兩家共用同一組編號空間，光看 product_code 會撈到
     * 另一家的商品（例如 u0000000053204 在 UNIQLO 是打褶寬版錐形褲、在 GU 是
     * 一件家居服）。商品的唯一鍵是 brand 加 product_code，跟爬蟲寫入時一致。
     *
     * 查詢先用 product_code 縮小範圍（那欄有索引，每個編號最多命中兩筆），
     * 再在記憶體裡用品牌配對。順序照傳入的順序排，收藏頁才維持使用者的收藏順序。
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
     * 取出某個分類底下還買得到的商品。
     *
     * 走 pivot join 直接查資料庫，不走清單頁那套預熱快取：清單頁是「促銷狀態」
     * 這種每天算一次就好的小集合，分類是商品本體的軸，一個大類可能上千件、
     * 還要跟品牌與分頁疊加，那是資料庫該做的事。
     *
     * 排序用官方在該分類內的權重，出來的順序就跟官網一致。
     */
    /**
     * @param  array<int, ProductTag>  $tags
     */
    public function getProductsByCategoryId(
        int $categoryId,
        array $tags = [],
        int $perPage = 24
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

        // 標籤要在查詢層篩，不能先取一頁再過濾——那會漏掉商品，頁數也會是錯的
        if (! empty($tags)) {
            $query->where(function ($group) use ($tags) {
                foreach ($tags as $tag) {
                    $tag->applyTo($group);
                }
            });
        }

        return $query
            ->orderBy('category_pivot.sort')
            ->orderBy('hmall_products.code')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * 依關鍵字搜尋商品，每個關鍵字都要命中才算符合。
     *
     * 比對品名、編號與商品掛的分類名稱。用 LIKE 而不是全文索引：前後都有萬用字元的
     * 比對本來就用不到 B-tree，為它加索引只會增加每日爬蟲的寫入成本。
     *
     * @param  array<int, string>  $keywords
     */
    public function searchByKeywords(array $keywords, int $perPage = 24): LengthAwarePaginator
    {
        $query = $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct');

        // 沒有關鍵字時要回空結果，不能因為少了 where 就把整張表撈出來
        if (empty($keywords)) {
            $query->whereRaw('1 = 0');
        }

        foreach ($keywords as $keyword) {
            $pattern = '%'.$this->escapeLikeWildcards($keyword).'%';

            $query->where(function ($subQuery) use ($pattern) {
                foreach (self::SEARCHABLE_COLUMNS as $column) {
                    $subQuery->orWhere($column, 'like', $pattern);
                }

                // 也比對商品掛的分類名稱，讓「外套」這種只出現在分類、
                // 不出現在品名裡的詞也搜得到。
                //
                // 這裡刻意用不相關的子查詢（不引用外層的 hmall_products），
                // MySQL 才能先把符合的商品 id 一次算完；寫成 whereExists 會變成
                // 每一筆商品各跑一次子查詢，實測慢十倍以上。
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
            // 還買得到的排前面，其次是評論多的
            ->orderByRaw('stockout_at IS NOT NULL')
            ->orderBy('evaluation_count', 'desc')
            ->orderBy('score', 'desc')
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * 跳脫 LIKE 的萬用字元，讓使用者輸入的 % 與 _ 當成一般文字比對。
     *
     * 反斜線要先跳脫，否則後面補上的跳脫字元會再被吃掉一次。
     */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function saveProductsFromV3($products, $brand = 'UNIQLO')
    {
        // 分類主檔整批先寫，一頁商品只打一次資料庫，而不是每個商品各寫十幾筆。
        // 回傳的 code 對 id 對照表給下面掛關聯用，省掉每個商品各查一次。
        $categoryIds = $this->saveCategoriesFromV3($products, $brand);

        collect($products)->each(function ($product) use ($brand, $categoryIds) {
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
                $model->new_at = $this->getCarbonOrNull($product->new ?? null);
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

                $model->save();

                $this->syncCategories($model, $product, $categoryIds);

                if (! $isChangedThePrice) {
                    return;
                }

                $hmallPriceHistory = new HmallPriceHistory();
                $hmallPriceHistory->min_price = $model->min_price;
                $hmallPriceHistory->max_price = $model->max_price;
                $model->hmallPriceHistories()->save($hmallPriceHistory);
            } catch (Throwable $e) {
                Log::error('saveProductsFromHmall error', [
                    'brand' => $brand,
                    'product_code' => $product->productCode,
                ]);

                report($e);
            }
        });
    }

    public function setStockoutHmallProducts($brand = 'UNIQLO', $updatedIsBefore = null)
    {
        if (is_null($updatedIsBefore)) {
            $updatedIsBefore = today();
        }

        $this->model
            ->whereNull('stockout_at')
            ->where('brand', $brand)
            ->where('updated_at', '<', $updatedIsBefore)
            ->update([
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
     * 官方每筆商品都帶完整的四層分類物件（code、name、parentCode 齊全），
     * 所以主檔不需要另外抓一支 endpoint。分類的身分是品牌加 code，
     * upsert 的比對鍵也是這兩欄。
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
     * 分類歸屬以四層陣列為準，categorySortList 只提供官方在該分類內的排序權重。
     * 回傳沒有分類時不動既有關聯：那比較可能是這次回傳缺漏，而不是商品真的被移出所有分類。
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
            ->unique(fn ($item) => "{$item['brand']}_{$item['productCode']}")
            ->sortByDesc('weightedViews');
    }

    private function getBrandFromUrl(string $fullPageUrl): string
    {
        return str_contains($fullPageUrl, '/gu-products/') ? 'GU' : 'UNIQLO';
    }

    private function getProductCodeFromUrl(string $fullPageUrl): string
    {
        return explode('/', $fullPageUrl)[2];
    }

    private function fetchRankedProducts(Collection $rank): Collection
    {
        if ($rank->isEmpty()) {
            return collect([]);
        }

        $productIdentifiers = $rank->map(fn ($item) => "{$item['brand']}_{$item['productCode']}");

        $orderClause = $rank->map(fn ($item) => "'{$item['brand']}_{$item['productCode']}'")
            ->join(',');

        $orderByField = sprintf('FIELD(CONCAT(brand, \'_\', product_code), %s)', $orderClause);

        return $this->model
            ->select(self::SELECT_COLUMNS_FOR_LIST)
            ->with('japanProduct')
            ->whereIn(DB::raw("CONCAT(brand, '_', product_code)"), $productIdentifiers->toArray())
            ->where('stock', 'Y')
            ->whereNull('stockout_at')
            ->orderByRaw($orderByField)
            ->get();
    }
}
