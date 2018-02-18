<?php

namespace App\Repositories;

use App\Product;
use App\ProductHistory;
use Exception;
use Yish\Generators\Foundation\Repository\Repository;

use function Functional\map;

class ProductRepository extends Repository
{
    protected $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function saveProductsFromUniqlo($products)
    {
        map($products, function ($product) {
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
                // $model->style_dicrionary_images = json_encode($product->style_dicrionary_images); //TODO:

                $model->save();

                $history = new ProductHistory;
                $history->price = $model->price;
                $model->histories()->save($history);
            } catch (Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
            }
        });
    }

    public function getProductMainImageUrl($product)
    {
        $color = $product->representativeSKU->color;
        $id = $product->id;

        return "http://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$color}_{$id}.jpg";
    }
}
