<?php

namespace App\Repositories;

use App\Product;
use App\ProductHistory;
use App\MultiBuyHistory;
use App\StyleDictionary;
use Exception;
use Yish\Generators\Foundation\Repository\Repository;

use function Functional\each;
use function Functional\map;

class ProductRepository extends Repository
{
    protected $product;
    protected $styleDictionary;

    public function __construct(Product $product, ProductHistory $productHistory, StyleDictionary $styleDictionary)
    {
        $this->product = $product;
        $this->productHistory = $productHistory;
        $this->styleDictionary = $styleDictionary;
    }

    public function saveProductsFromUniqlo($products)
    {
        each($products, function ($product) {
            try {
                $model = Product::firstOrNew(['id' => $product->id]);

                $model->id = $product->id;
                $model->name = $product->name;
                $model->category_id = $product->parentCategoryId;
                $model->categories = json_encode($product->categories);
                $model->ancestors = json_encode($product->ancestors);
                $model->main_image_url = $this->getProductMainImageUrl($product);
                $model->comment = $product->catchCopy;
                $model->price = $product->representativeSKU->salePrice;
                $model->flags = json_encode($product->flags);
                $model->representative_sku_flags = json_encode($product->representativeSKU->flags);
                $model->limit_sales_end_msg = $product->representativeSKU->limitSalesEndMsg;
                $model->new = $product->new;
                $model->sale = $this->getSaleStatus($product);
                $model->sub_images = json_encode($product->subImages);
                $model->colors = json_encode($product->colors);

                $model->save();

                $history = new ProductHistory;
                $history->price = $model->price;
                $model->histories()->save($history);
            } catch (Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
            }
        });
    }

    public function getProductMainImageUrl($product)
    {
        $color = $product->representativeSKU->color;
        $id = $product->id;

        return "http://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$color}_{$id}.jpg";
    }

    public function getSaleStatus($product)
    {
        $flags = $product->representativeSKU->flags;

        foreach ($flags as $flag) {
            if ($flag->name == 'SALE') {
                return true;
            }
        }

        return false;
    }

    public function saveStyleDictionaryFromUniqlo($detail, $imgDir)
    {
        try {
            $model = StyleDictionary::firstOrNew(['id' => $detail->id]);

            $model->id = $detail->id;
            $model->fnm = $detail->fnm;
            $model->image_url = "http://www.uniqlo.com/{$imgDir}{$model->fnm}-xl.jpg";
            $model->detail_url = "http://www.uniqlo.com/tw/stylingbook/detail.html#/detail/{$model->id}";

            $model->save();
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }

        each($detail->list, function ($person) use ($model) {
            try {
                $products = array_pluck($person->products, 'pub');
                $model->products()->syncWithoutDetaching($products);
            } catch (Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
            }
        });
    }

    public function getStyleDictionaries(Product $product)
    {
        return $product->styleDictionaries;
    }

    /**
     * Set stockout status to the products.
     *
     * @return void
     */
    public function setStockoutProducts()
    {
        $this->product->whereDoesntHave('histories', function ($query) {
            $query->whereDate('created_at', today()->toDateString());
        })->update(['stockout' => true]);

        $this->product->whereHas('histories', function ($query) {
            $query->whereDate('created_at', today()->toDateString());
        })->update(['stockout' => false]);
    }

    /**
     * Get all of the stockout products.
     *
     * @return array|null Array of Product models
     */
    public function getStockoutProductsOrderByDate()
    {
        // TODO: Pagination and Caching
        // TODO: Move to ProductHistory repository
        $productIds = ProductHistory::selectRaw('product_id, max(created_at)')
            ->groupBy('product_id')
            ->having('max(created_at)', '<', today()->subDay())
            ->orderBy('max(created_at)', 'desc')
            ->get()->map(function ($item, $key) {
                return $item->product_id;
            });

        $products = $this->product->whereIn('id', $productIds)->get();
        $sortedProducts = map($productIds, function ($id) use ($products) {
            $products = $products->keyBy('id');
            return $products->get($id);
        });

        return collect($sortedProducts);
    }

    public function getProductsByIds($productIds)
    {
        return $products = $this->product->whereIn('id', $productIds)->get();
    }

    /**
     * Get limited offer products.
     *
     * @return array|null Limited offer products
     */
    public function getLimitedOfferProducts()
    {
        $products = $this->product
            ->where('limit_sales_end_msg', '!=', '')
            ->where('stockout', false)
            ->orderBy('limit_sales_end_msg')
            ->orderByRaw('price/max_price')
            ->get();

        return $products;
    }

    /**
     * Get sale products.
     *
     * @return array|null Sale products
     */
    public function getSaleProducts()
    {
        $products = $this->product
            ->where('sale', true)
            ->where('stockout', false)
            ->orderByRaw('price/max_price')
            ->get();

        return $products;
    }

    /**
     * Get the min price and the max price from the products table.
     *
     * @param array $prices
     * @return void
     */
    public function setMinPricesAndMaxPrices($prices)
    {
        $prices->each(function ($price) {
            $product = $this->product->find($price->product_id);

            if (!empty($product)) {
                $product->min_price = $price->min_price;
                $product->max_price = $price->max_price;

                $product->save();
            }
        });
    }

    /**
     * Get all of the MULTI_BUY Products.
     *
     * @return array
     */
    public function getMultiBuyProductIds()
    {
        $ids = $this->product->select('id')
            ->where('representative_sku_flags', 'like', '%MULTI_BUY%')
            ->pluck('id');

        return $ids;
    }

    /**
     * Save the MULTI_BUY promo.
     *
     * @param string $id
     * @param string $promo
     * @return void
     */
    public function saveMultiBuyPromo($id, $promo)
    {
        $product = $this->product->find($id);
        $product->multi_buy = $promo;
        $product->save();

        $multiBuy = new MultiBuyHistory;
        $multiBuy->product_id = $id;
        $multiBuy->multi_buy = $promo;
        $multiBuy->save();
    }
}
