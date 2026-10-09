<?php

namespace App\Repositories;

use App\Models\JapanProduct;
use App\Support\ProductSaveResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JapanProductRepository
{
    public function __construct(protected JapanProduct $model) {}

    /**
     * 回傳哪幾件寫不進去：知道 l1Id 的從下架判定排除，拿不到的只能整輪不做下架判定。
     */
    public function saveProducts($products, $brand = 'UNIQLO'): ProductSaveResult
    {
        $failedIds = [];
        $unidentifiedFailureCount = 0;

        collect($products)->each(function ($product) use ($brand, &$failedIds, &$unidentifiedFailureCount) {
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

                // 日本的商品列表沒有庫存欄位，出現在回傳裡就是還在賣；
                // 不清掉的話，被標過下架的商品重新上架也永遠顯示下架
                $model->stockout_at = null;
                // 下架判定以 updated_at 認定「這輪還看得到」，資料完全沒變時
                // save() 不會碰它，商品會被誤標下架
                $model->updated_at = now();

                $model->save();
            } catch (Throwable $e) {
                $l1Id = $product->l1Id ?? null;

                if (is_string($l1Id) && $l1Id !== '') {
                    $failedIds[] = $l1Id;
                } else {
                    $unidentifiedFailureCount++;
                }

                Log::error('saveJapanProducts error', [
                    'brand' => $brand,
                    'l1Id' => is_string($l1Id) ? $l1Id : null,
                ]);

                report($e);
            }
        });

        return new ProductSaveResult(array_values(array_unique($failedIds)), $unidentifiedFailureCount);
    }

    /**
     * @param  array<int, string>  $excludedIds  寫入失敗、但官網其實還在賣的 l1Id
     */
    public function setStockoutProducts($brand = 'UNIQLO', $updatedIsBefore = null, array $excludedIds = [])
    {
        if (is_null($updatedIsBefore)) {
            $updatedIsBefore = today();
        }

        $query = $this->model
            ->whereNull('stockout_at')
            ->where('brand', $brand)
            ->where('updated_at', '<', $updatedIsBefore);

        if ($excludedIds !== []) {
            $query->whereNotIn('l1Id', $excludedIds);
        }

        $query->update([
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
