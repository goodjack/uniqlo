<?php

namespace App\Repositories;

use App\Models\JapanProduct;
use Illuminate\Support\Facades\Log;
use Throwable;

class JapanProductRepository
{
    public function __construct(protected JapanProduct $model)
    {
    }

    public function saveProducts($products, $brand = 'UNIQLO'): void
    {
        collect($products)->each(function ($product) use ($brand) {
            try {
                /** @var JapanProduct $model */
                $model = JapanProduct::firstOrNew([
                    'brand' => $brand,
                    'l1Id' => $product->l1Id,
                ]);

                $model->product_id = $product->productId;
                $model->name = $product->name;
                $model->gender_category = $product->genderCategory;
                $model->rating_average = $product->rating->average;
                $model->rating_count = $product->rating->count;

                $model->main_images = json_decode(json_encode($product->images->main), true);
                $model->sub_images = json_decode(json_encode($product->images->sub), true);
                $model->sub_videos = json_decode(json_encode($product->images->sub), true);

                $model->save();
            } catch (Throwable $e) {
                Log::error('saveProductsFromHmall error', [
                    'brand' => $brand,
                    'l1Id' => $product->l1Id,
                ]);

                report($e);
            }
        });
    }
}
