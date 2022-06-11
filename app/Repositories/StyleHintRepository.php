<?php

namespace App\Repositories;

use App\StyleHint;
use App\StyleHintItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yish\Generators\Foundation\Repository\Repository;

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
            $model->user_info = data_get($result, 'userInfo');
            $model->hashtags = data_get($result, 'hashtags');
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
}
