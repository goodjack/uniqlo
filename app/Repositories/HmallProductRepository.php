<?php

namespace App\Repositories;

use App\Models\HmallPriceHistory;
use App\Models\HmallProduct;
use App\Models\Product;
use Carbon\Carbon;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\FilterExpressionList;
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

    public function saveProductsFromV3($products, $brand = 'UNIQLO')
    {
        collect($products)->each(function ($product) use ($brand) {
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

                $model->save();

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
            ->orderBy('id', 'desc')
            ->get();
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
        $dailyAnalyticsData = $this->fetchMostVisitedProducts(1, 100);
        $weeklyAnalyticsData = $this->fetchMostVisitedProducts(7, 100);

        $rank = $this->calculateProductRanking($dailyAnalyticsData, $weeklyAnalyticsData);

        return $this->fetchRankedProducts($rank);
    }

    private function calculateProductRanking(Collection $dailyData, Collection $weeklyData): Collection
    {
        return $dailyData->map(function ($dailyViews, $fullPageUrl) use ($weeklyData) {
            $weeklyViews = $weeklyData[$fullPageUrl] ?? 0;
            $weeklyAverageViews = $weeklyViews / 7;
            $weightedViews = ($dailyViews * 0.7) + ($weeklyAverageViews * 0.3);

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
