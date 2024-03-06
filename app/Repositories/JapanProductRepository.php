<?php

namespace App\Repositories;

use App\Models\JapanProduct;
use Illuminate\Support\Facades\DB;
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
                $model = $this->model->firstOrNew([
                    'brand' => $brand,
                    'l1Id' => $product->l1Id,
                ]);

                $model->product_id = $product->productId;
                $model->name = $product->name;
                $model->gender_category = $product->genderCategory;
                $model->rating_average = $product->rating->average;
                $model->rating_count = $product->rating->count;

                $model->prices = $this->getPrices($model, $product);
                $model->lowest_record_price = $this->getLowestRecordPrice($model, $product->prices->base->value);
                $model->highest_record_price = $this->getHighestRecordPrice($model, $product->prices->base->value);

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

    public function setStockoutProducts($brand = 'UNIQLO', $updatedIsBefore = null)
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

    private function getPrices($model, $product)
    {
        $prices = $model->prices ?? [];

        $prices[$product->priceGroup] = $product->prices->base->value;

        return $prices;
    }

    private function getLowestRecordPrice($model, $price)
    {
        $lowestRecordPrice = $model->lowest_record_price;

        if (empty($lowestRecordPrice)) {
            return $price;
        }

        return min($lowestRecordPrice, $price);
    }

    private function getHighestRecordPrice($model, $price)
    {
        $highestRecordPrice = $model->highest_record_price;

        if (empty($highestRecordPrice)) {
            return $price;
        }

        return max($highestRecordPrice, $price);
    }
}
