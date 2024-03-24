<?php

namespace App\Repositories;

use App\Models\StyleHint;
use App\Models\StyleHintItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

    public function saveStyleHintsFromUgc($content, string $brand = 'UNIQLO')
    {
        $country = 'tw';
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
        ];

        try {
            $model = $this->model->updateOrCreate(
                [
                    'brand' => $brand,
                    'country' => $country,
                    'outfit_id' => $content->id_ugc_dist_content,
                ],
                $modelData,
            );
        } catch (Throwable $e) {
            Log::error('saveStyleHintsFromUgc save error', [
                'brand' => $brand,
                'country' => $country,
                'content' => $content,
            ]);

            report($e);

            if (is_null($model->id)) {
                return;
            }
        }

        $originalProductIds = collect(data_get($content, 'products.*.ugc_item.product_id'));

        $upsertData = $originalProductIds->map(function ($originalProductId) use ($model) {
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
                ];
            } catch (Throwable $e) {
                Log::error('saveStyleHintsFromUgc StyleHintItem upsert error', [
                    'style_hint_id' => $model->id,
                    'original_product_id' => $originalProductId,
                ]);

                report($e);

                return null;
            }
        })->filter()->toArray();

        try {
            if ($upsertData) {
                StyleHintItem::upsert($upsertData, uniqueBy: ['style_hint_id', 'code', 'original_product_id']);
            }
        } catch (Throwable $e) {
            Log::error('saveStyleHintsFromUgc StyleHintItem upsert error', [
                'style_hint_id' => $model->id,
                'upsertData' => $upsertData,
            ]);

            report($e);
        }
    }
}
