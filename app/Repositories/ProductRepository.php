<?php

namespace App\Repositories;

use App\Product;
use App\ProductHistory;
use App\StyleDictionary;
use Exception;
use Yish\Generators\Foundation\Repository\Repository;

use function Functional\each;

class ProductRepository extends Repository
{
    protected $product;
    protected $styleDictionary;

    public function __construct(Product $product, StyleDictionary $styleDictionary)
    {
        $this->product = $product;
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
                $model->limit_sales_end_msg = $product->representativeSKU->limitSalesEndMsg;
                $model->new = $product->new;
                $model->sub_images = json_encode($product->subImages);
                // $model->style_dictionary_images = json_encode($product->style_dictionary_images); //TODO:

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
}
