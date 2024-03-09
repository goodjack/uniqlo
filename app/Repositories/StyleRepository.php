<?php

namespace App\Repositories;

use App\Models\Style;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StyleRepository extends Repository
{
    protected $model;

    public function __construct(Style $style)
    {
        $this->model = $style;
    }

    public function getSuggestProductIds($styles, $havingProducts = 2)
    {
        $styleIds = $styles->pluck('id');

        return DB::table('product_style')
            ->select(DB::raw('count(*) as product_count, product_id'))
            ->whereIn('style_id', $styleIds)
            ->groupBy('product_id')
            ->having('product_count', '>', $havingProducts)
            ->orderBy('product_count', 'desc')
            ->get()
            ->pluck('product_id');
    }

    public function saveStyleFromOfficialStyling($styleId, $result, string $brand = 'UNIQLO'): void
    {
        if (isset($result->error) || empty($result->style)) {
            Log::info("Error style ID: {$styleId}");

            return;
        }

        $model = $this->model->firstOrNew(['id' => $result->style->style_id]);

        try {
            $imageOriginal = $result->style->image_original;

            preg_match(
                '/https:\/\/api.fastretailing.com\/ugc\/v1\/(uq|gu)\/gl\/OFFICIAL_IMAGES\/(.*)/',
                $imageOriginal,
                $matches
            );

            $model->id = $result->style->style_id;
            $model->type = ($brand === 'GU') ? 'gu' : 'uq';
            $model->dpt_id = $result->style->gender_id;
            $model->image_path = ($matches[2] ?? $imageOriginal);
            $model->image_url = null;
            $model->detail_url = null;

            $model->save();
        } catch (Throwable $e) {
            Log::error('saveStyleFromOfficialStyling Style save error', [
                'styleId' => $styleId,
                'result' => $result,
                'brand' => $brand,
            ]);

            report($e);
        }

        try {
            $codes = collect(data_get($result, 'coordinate.*.products.*.product_id'))->all();

            /** @var HmallProductRepository */
            $hmallProductRepository = app(HmallProductRepository::class);
            $hmallProductIds = $hmallProductRepository->getIdsFromCodes($codes);

            $model->hmallProducts()->syncWithoutDetaching($hmallProductIds);
        } catch (Throwable $e) {
            Log::error('saveStyleFromOfficialStyling HmallProduct sync error', [
                'styleId' => $styleId,
                'result' => $result,
                'brand' => $brand,
            ]);

            report($e);
        }
    }
}
