<?php

namespace App\Repositories;

use App\Models\StyleHint;
use App\Models\StyleHintItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StyleHintRepository extends Repository
{
    protected $model;

    public function __construct(StyleHint $styleHint)
    {
        $this->model = $styleHint;
    }

    public function getExistStyleHintOutfitIds(string $country, Collection $outfitIds): array
    {
        return $this->model
            ->select('outfit_id')
            ->where('country', $country)
            ->whereIn('outfit_id', $outfitIds)
            ->get()
            ->pluck('outfit_id')
            ->toArray();
    }

    public function saveStyleHints($country, $styleHintSummary, $result)
    {
        $model = $this->model->firstOrNew(['country' => $country, 'outfit_id' => $styleHintSummary->outfitId]);

        try {
            $model->style_image_url = data_get($styleHintSummary, 'styleImageUrl');
            $model->original_source_url = data_get($styleHintSummary, 'originalSourceUrl');
            $model->department_id = data_get($styleHintSummary, 'departmentId');

            $model->model_height = data_get($result, 'modelHeight');
            $model->user_id = data_get($result, 'userInfo.id');
            $model->user_name = data_get($result, 'userInfo.name');
            $model->user_image = data_get($result, 'userInfo.image');
            $model->user_type = data_get($result, 'userInfo.userType');
            $model->store_region = data_get($result, 'userInfo.region');
            $model->store_name = data_get($result, 'userInfo.storeName');
            $model->comment = data_get($result, 'userInfo.content');
            $model->user_info = null;
            $model->hashtags = null;
            $model->gender = data_get($result, 'gender');

            $publishedAt = data_get($result, 'publishedAt');
            $model->published_at = $publishedAt ? Carbon::parse($publishedAt) : null;

            $model->save();
        } catch (Throwable $e) {
            Log::error('saveStyleHints save error', [
                'country' => $country,
                'styleHintSummary' => $styleHintSummary,
                'result' => $result,
            ]);

            report($e);

            if (is_null($model->id)) {
                return;
            }
        }

        $originalProductIds = collect(data_get($result, 'models.*.products.*.productId'));

        $originalProductIds->each(function ($originalProductId) use ($model) {
            try {
                preg_match('/E([0-9]+)/', $originalProductId, $matches);
                $code = $matches[1] ?? null;

                if (is_null($code)) {
                    Log::error('saveStyleHints code not found', [
                        'originalProductId' => $originalProductId,
                    ]);
                }

                StyleHintItem::firstOrCreate([
                    'style_hint_id' => $model->id,
                    'code' => $code,
                    'original_product_id' => $originalProductId,
                ]);
            } catch (Throwable $e) {
                Log::error('saveStyleHints StyleHintItem firstOrCreate error', [
                    'style_hint_id' => $model->id,
                    'original_product_id' => $originalProductId,
                ]);

                report($e);
            }
        });
    }

    /**
     * 回應沒帶辨識結果時不放這欄，已存的就不會被蓋掉。
     *
     * @return array{detected_items?: array<int, array{category: string, color: string}>}
     */
    private function detectedItemsData(object $content): array
    {
        $attributes = data_get($content, 'attributes');

        return is_iterable($attributes)
            ? ['detected_items' => StyleHint::detectedItemsFromUgc($attributes)]
            : [];
    }

    /**
     * 穿搭裡每件商品穿的尺寸，以商品編號（貨號-色號）為鍵。
     *
     * @return array<string, string>
     */
    private function itemSizesFromUgc(object $content): array
    {
        $sizes = [];

        foreach (data_get($content, 'products', []) ?? [] as $product) {
            $productId = data_get($product, 'ugc_item.product_id');
            $size = data_get($product, 'ugc_item.item_size');

            if (is_string($productId) && is_string($size) && $size !== '') {
                $sizes[$productId] = $size;
            }
        }

        return $sizes;
    }

    public function saveStyleHintsFromUgc(array $contentList, string $brand = 'UNIQLO')
    {
        $country = 'tw';
        $upsertData = [];

        $existingStyleHints = $this->model
            ->whereIn('outfit_id', array_column($contentList, 'id_ugc_dist_content'))
            ->where('brand', $brand)
            ->where('country', $country)
            ->get();

        foreach ($contentList as $content) {
            $outfitId = data_get($content, 'id_ugc_dist_content');

            if (blank($outfitId)) {
                Log::error('saveStyleHintsFromUgc content missing id - skipped', [
                    'brand' => $brand,
                    'content' => $content,
                ]);

                continue;
            }

            $publishedAt = data_get($content, 'updated_date');

            $modelData = [
                'style_image_url' => data_get($content, 'image_original'),
                'original_source_url' => data_get($content, 'link_to_url'),
                'department_id' => null,
                'model_height' => data_get($content, 'model_height'),
                'user_id' => data_get($content, 'author.id_ugc_author_data'),
                'user_name' => data_get($content, 'author.nick_name'),
                'user_image' => data_get($content, 'author.picture_url'),
                'user_type' => data_get($content, 'author.user_type'),
                'store_region' => data_get($content, 'author.store_info.region_name'),
                'store_name' => data_get($content, 'author.store_info.store_name'),
                'comment' => data_get($content, 'description'),
                'user_info' => null,
                'hashtags' => null,
                'gender' => data_get($content, 'gender'),
                'published_at' => $publishedAt ? Carbon::parse($publishedAt) : null,
                ...$this->detectedItemsData($content),
            ];

            $model = $existingStyleHints->firstWhere('outfit_id', $outfitId);

            try {
                if ($model) {
                    $model->update($modelData);
                } else {
                    $modelData = array_merge([
                        'brand' => $brand,
                        'country' => $country,
                        'outfit_id' => $outfitId,
                    ], $modelData);

                    $model = $this->model->create($modelData);
                }
            } catch (Throwable $e) {
                Log::error('saveStyleHintsFromUgc save error', [
                    'brand' => $brand,
                    'country' => $country,
                    'content' => $content,
                ]);

                report($e);
            }

            if (is_null($model?->id)) {
                continue;
            }

            $originalProductIds = collect(data_get($content, 'products.*.ugc_item.product_id'));
            $itemSizes = $this->itemSizesFromUgc($content);

            $upsertData = array_merge($upsertData, $originalProductIds->map(function ($originalProductId) use ($model, $itemSizes) {
                try {
                    preg_match('/([0-9]+)-/', $originalProductId, $matches);
                    $code = $matches[1] ?? null;

                    if (is_null($code)) {
                        Log::error('saveStyleHintsFromUgc code not found', [
                            'original_product_id' => $originalProductId,
                        ]);
                    }

                    return [
                        'style_hint_id' => $model->id,
                        'code' => $code,
                        'original_product_id' => $originalProductId,
                        'item_size' => $itemSizes[$originalProductId] ?? null,
                    ];
                } catch (Throwable $e) {
                    Log::error('saveStyleHintsFromUgc StyleHintItem upsert error', [
                        'style_hint_id' => $model->id,
                        'original_product_id' => $originalProductId,
                    ]);

                    report($e);

                    return null;
                }
            })->filter()->toArray());
        }

        try {
            if ($upsertData) {
                // 回應沒帶尺寸時不蓋掉已存的尺寸，跟辨識結果一樣
                StyleHintItem::upsert(
                    $upsertData,
                    uniqueBy: ['style_hint_id', 'code', 'original_product_id'],
                    update: ['item_size' => DB::raw('COALESCE(VALUES(`item_size`), `item_size`)')],
                );
            }
        } catch (Throwable $e) {
            Log::error('saveStyleHintsFromUgc StyleHintItem upsert error', [
                'upsertData' => $upsertData,
            ]);

            report($e);
        }
    }
}
