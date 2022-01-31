<?php

namespace App\Repositories;

use App\HmallPriceHistory;
use App\HmallProduct;
use Carbon\Carbon;
use Throwable;
use Yish\Generators\Foundation\Repository\Repository;

class HmallProductRepository extends Repository
{
    protected $model;

    public function __construct(HmallProduct $model, HmallPriceHistory $hmallPriceHistory)
    {
        $this->model = $model;
        $this->hmallPriceHistory = $hmallPriceHistory;
    }

    public function saveProductsFromUniqloHmall($products)
    {
        collect($products)->each(function ($product) {
            try {
                /** @var HmallProduct $model */
                $model = $this->model->firstOrNew(['product_code' => $product->productCode]);

                $model->code = $product->code;
                $model->product_code = $product->productCode;
                $model->oms_product_code = $product->omsProductCode;
                $model->name = $product->name;
                $model->product_name = $product->productName;
                $model->prices = json_encode($product->prices);
                $model->min_price = $product->minPrice;
                $model->max_price = $product->maxPrice;
                $model->lowest_record_price = $this->getLowestRecordPrice($model, $product->minPrice);
                $model->highest_record_price = $this->getHighestRecordPrice($model, $product->maxPrice);
                $model->origin_price = $product->originPrice;
                $model->price_color = $product->priceColor;
                $model->identity = json_encode($product->identity);
                $model->label = $product->label;
                $model->time_limited_begin = $this->getCarbonOrNull($product->timeLimitedBegin);
                $model->time_limited_end = $this->getCarbonOrNull($product->timeLimitedEnd);
                $model->score = $product->score;
                $model->size_score = $product->sizeScore;
                $model->evaluation_count = $product->evaluationCount;
                $model->sales = $product->sales;
                $model->new = $product->new;
                $model->season = $product->season;
                $model->style_text = json_encode($product->styleText);
                $model->color_nums = json_encode($product->colorNums);
                $model->color_pic = json_encode($product->colorPic);
                $model->chip_pic = json_encode($product->chipPic);
                $model->main_first_pic = $product->mainPic;
                $model->size = json_encode($product->size);
                $model->min_size = $product->minSize;
                $model->max_size = $product->maxSize;
                $model->gender = $product->gender;
                $model->sex = $product->sex;
                $model->material = $product->material;
                $model->stock = $product->stock;

                if ($model->stock === 'Y' && $product->stock === 'N') {
                    $model->stockout_at = now();
                }

                if ($model->stock === 'N' && $product->stock === 'Y') {
                    $model->stockout_at = null;
                }

                $model->save();

                if ($this->hasNotChangeThePrice($model)) {
                    return;
                }

                $hmallPriceHistory = new HmallPriceHistory();
                $hmallPriceHistory->min_price = $model->min_price;
                $hmallPriceHistory->max_price = $model->max_price;
                $model->hmallPriceHistories()->save($hmallPriceHistory);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public function updateProductDescriptionsFromUniqloSpu(HmallProduct $hmallProduct, $instruction, $sizeChart, bool $updateTimestamps = false)
    {
        $hmallProduct->instruction = $instruction;
        $hmallProduct->size_chart = $sizeChart;

        $hmallProduct->timestamps = $updateTimestamps;
        $hmallProduct->save();
    }

    public function getAllProductsForSitemap()
    {
        return $this->model
            ->select(['id', 'product_code', 'updated_at'])
            ->orderBy('id', 'desc')
            ->get();
    }

    private function getLowestRecordPrice($model, $minPrice)
    {
        $lowestRecordPrice = $model->lowest_record_price;

        if (empty($lowestRecordPrice)) {
            return $minPrice;
        }

        return min($lowestRecordPrice, $minPrice);
    }

    private function getHighestRecordPrice($model, $maxPrice)
    {
        $highestRecordPrice = $model->highest_record_price;

        if (empty($highestRecordPrice)) {
            return $maxPrice;
        }

        return max($highestRecordPrice, $maxPrice);
    }

    private function getCarbonOrNull($unixTimestampInMilliseconds)
    {
        if (empty($unixTimestampInMilliseconds)) {
            return null;
        }

        return Carbon::createFromTimestampMs($unixTimestampInMilliseconds);
    }

    private function hasNotChangeThePrice($model)
    {
        $latestHmallPriceHistory = $model->hmallPriceHistories()->latest()->first();

        if (is_null($latestHmallPriceHistory)) {
            return false;
        }

        return $model->min_price == $latestHmallPriceHistory->min_price &&
            $model->max_price == $latestHmallPriceHistory->max_price;
    }
}
