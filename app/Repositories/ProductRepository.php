<?php

namespace App\Repositories;

use App\Models\HmallProduct;
use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\Style;
use App\Models\StyleDictionary;
use Cache;
use Throwable;

class ProductRepository extends Repository
{
    private const CACHE_KEY_LIMITED_OFFER = 'product:limited_offer';

    private const CACHE_KEY_MULTI_BUY = 'product:multi_buy';

    private const CACHE_KEY_SALE = 'product:sale';

    private const CACHE_KEY_STOCKOUT = 'product:stockout';

    private const CACHE_KEY_NEW = 'product:new';

    private const CACHE_KEY_MOST_REVIEWED = 'product:most_reviewed';

    private const SELECT_COLUMNS_FOR_PRODUCT_LIST = [
        'id',
        'name',
        'category_id',
        'main_image_url',
        'price',
        'min_price',
        'max_price',
        'limit_sales_end_msg',
        'multi_buy',
        'new',
        'sale',
        'review_count',
        'review_rating',
    ];

    protected $product;

    protected $styleDictionary;

    protected $style;

    public function __construct(
        Product $product,
        ProductHistory $productHistory,
        StyleDictionary $styleDictionary,
        Style $style
    ) {
        $this->product = $product;
        $this->productHistory = $productHistory;
        $this->styleDictionary = $styleDictionary;
        $this->style = $style;
    }

    public function saveStyleFromUniqloStyleBook($result)
    {
        try {
            $model = $this->style->firstOrNew(['id' => $result->photo->styleId]);
            $firstItem = $result->coordinates[0]->items[0];

            $model->id = $result->photo->styleId;
            $model->dpt_id = $result->photo->dptId;
            $model->image_path = $firstItem->image_path;
            $model->image_url = "https://im.uniqlo.com/style/{$model->image_path}-l.jpg";
            $model->detail_url = "http://www.uniqlo.com/tw/stylingbook/sp/style/{$model->id}";

            $model->save();
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $productIds = data_get($result, 'coordinates.*.items.*.sku_code');
            $model->products()->syncWithoutDetaching($productIds);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function getStyleDictionaries(Product $product)
    {
        return $product->styleDictionaries;
    }

    public function getStyles(Product $product)
    {
        return $product->styles;
    }

    /**
     * Get the stockout products.
     *
     * @return Collection|Product[]
     */
    public function getStockoutProducts()
    {
        if (! Cache::has(self::CACHE_KEY_STOCKOUT)) {
            $this->setStockoutProductsCache();
        }

        return Cache::get(self::CACHE_KEY_STOCKOUT);
    }

    /**
     * Put the stockout products to the cache.
     */
    public function setStockoutProductsCache()
    {
        // TODO: Pagination
        // TODO: Move to ProductHistory repository
        $productIds = ProductHistory::selectRaw('product_id, max(created_at)')
            ->groupBy('product_id')
            ->having('max(created_at)', '<', today())
            ->having('max(created_at)', '>=', today()->subWeek())
            ->orderBy('max(created_at)', 'desc')
            ->pluck('product_id');

        $products = $this->product
            ->select(self::SELECT_COLUMNS_FOR_PRODUCT_LIST)
            ->whereIn('id', $productIds)->get()->keyBy('id');
        $sortedProducts = $productIds->map(function ($id) use ($products) {
            return $products->get($id);
        });

        Cache::forever(self::CACHE_KEY_STOCKOUT, $sortedProducts);
    }

    public function getProductsByIds($productIds)
    {
        return $this->product->whereIn('id', $productIds)->get();
    }

    /**
     * Get limited offer products.
     *
     * @return Collection|Product[] Limited offer products
     */
    public function getLimitedOfferProducts()
    {
        if (! Cache::has(self::CACHE_KEY_LIMITED_OFFER)) {
            $this->setLimitedOfferProductsCache();
        }

        return Cache::get(self::CACHE_KEY_LIMITED_OFFER);
    }

    /**
     * Put limited offer products to the cache.
     */
    public function setLimitedOfferProductsCache()
    {
        $products = $this->product
            ->select(self::SELECT_COLUMNS_FOR_PRODUCT_LIST)
            ->where('limit_sales_end_msg', '!=', '')
            ->where('stockout', false)
            ->orderBy('limit_sales_end_msg')
            ->orderByRaw('price/max_price')
            ->orderBy('review_count', 'desc')
            ->orderBy('review_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_LIMITED_OFFER, $products);
    }

    /**
     * Get MULTI_BUY products.
     *
     * @return Collection|Product[] MULTI_BUY products
     */
    public function getMultiBuyProducts()
    {
        if (! Cache::has(self::CACHE_KEY_MULTI_BUY)) {
            $this->setMultiBuyProductsCache();
        }

        return Cache::get(self::CACHE_KEY_MULTI_BUY);
    }

    /**
     * Put the MULTI_BUY products to the cache.
     */
    public function setMultiBuyProductsCache()
    {
        $products = $this->product
            ->select(self::SELECT_COLUMNS_FOR_PRODUCT_LIST)
            ->whereNotNull('multi_buy')
            ->where('stockout', false)
            ->orderBy('multi_buy')
            ->orderBy('review_count', 'desc')
            ->orderBy('review_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_MULTI_BUY, $products);
    }

    /**
     * Get sale products.
     *
     * @return Collection|Product[] Sale products
     */
    public function getSaleProducts()
    {
        if (! Cache::has(self::CACHE_KEY_SALE)) {
            $this->setSaleProductsCache();
        }

        return Cache::get(self::CACHE_KEY_SALE);
    }

    /**
     * Put sale products to the cache.
     */
    public function setSaleProductsCache()
    {
        $products = $this->product
            ->select(self::SELECT_COLUMNS_FOR_PRODUCT_LIST)
            ->where('sale', true)
            ->where('stockout', false)
            ->orderByRaw('price/max_price')
            ->orderBy('review_count', 'desc')
            ->orderBy('review_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_SALE, $products);
    }

    /**
     * Get new products.
     *
     * @return Collection|Product[] New products
     */
    public function getNewProducts()
    {
        if (! Cache::has(self::CACHE_KEY_NEW)) {
            $this->setNewProductsCache();
        }

        return Cache::get(self::CACHE_KEY_NEW);
    }

    /**
     * Put new products to the cache.
     */
    public function setNewProductsCache()
    {
        $products = $this->product
            ->select(self::SELECT_COLUMNS_FOR_PRODUCT_LIST)
            ->where('new', true)
            ->where('stockout', false)
            ->orderByRaw('price/max_price')
            ->orderBy('review_count', 'desc')
            ->orderBy('review_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_NEW, $products);
    }

    /**
     * Get most reviewed products.
     *
     * @return Collection|Product[] New products
     */
    public function getMostReviewedProducts()
    {
        if (! Cache::has(self::CACHE_KEY_MOST_REVIEWED)) {
            $this->setMostReviewedProductsCache();
        }

        return Cache::get(self::CACHE_KEY_MOST_REVIEWED);
    }

    /**
     * Put most reviewed products to the cache.
     */
    public function setMostReviewedProductsCache()
    {
        $products = $this->product
            ->select(self::SELECT_COLUMNS_FOR_PRODUCT_LIST)
            ->where('review_count', '>=', function ($query) {
                $query->selectRaw('MAX(review_count) as max_review_count')
                    ->from('products')
                    ->where('stockout', false)
                    ->groupByRaw('LEFT(category_id, 3)')
                    ->orderBy('max_review_count')
                    ->limit(1);
            })
            ->where('stockout', false)
            ->orderBy('review_count', 'desc')
            ->orderBy('review_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::forever(self::CACHE_KEY_MOST_REVIEWED, $products);
    }

    public function getRelatedProducts(Product $product, $excludeItself = true)
    {
        $relatedId = substr($product->id, 0, 6);

        $query = $this->product
            ->select(self::SELECT_COLUMNS_FOR_PRODUCT_LIST)
            ->where(function ($query) use ($relatedId, $product) {
                $query->where('id', 'like', "{$relatedId}%")
                    ->orWhere('name', $product->name);
            });

        if ($excludeItself) {
            $query->where('id', '<>', $product->id);
        }

        return $query->orderBy('price')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getRelatedProductsForHmallProduct(HmallProduct $hmallProduct)
    {
        $product = $this->product->where('id', $hmallProduct->code)->first();

        if (empty($product)) {
            $relatedName = $hmallProduct->name;

            $product = $this->product
                ->where('name', 'like', "%{$relatedName}%")
                ->orderByRaw('CASE WHEN `name` LIKE ? THEN 0 WHEN `name` LIKE ? THEN 1 ELSE 2 END', ["%{$hmallProduct->gender}%", "%{$hmallProduct->sex}%"])
                ->first();
        }

        if (empty($product)) {
            return collect([]);
        }

        return $this->getRelatedProducts($product, false);
    }

    public function getAllProductsForSitemap()
    {
        return $this->product
            ->select(['id', 'updated_at'])
            ->get();
    }
}
