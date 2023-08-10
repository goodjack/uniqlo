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

    public function saveStyleFromUniqloStyleBook($styleId, $result)
    {
        if ($result->error === 'expired' && empty($result->photo)) {
            Log::info("Expired style ID: {$styleId}");

            return;
        }

        $model = $this->model->firstOrNew(['id' => $result->photo->styleId]);

        try {
            $firstItem = $result->coordinates[0]->items[0];

            $model->id = $result->photo->styleId;
            $model->dpt_id = $result->photo->dptId;
            $model->image_path = $firstItem->image_path;
            $model->image_url = "https://im.uniqlo.com/style/{$model->image_path}-xxm.jpg";
            $model->detail_url = "https://www.uniqlo.com/tw/stylingbook/sp/style/{$model->id}";

            $model->save();
        } catch (Throwable $e) {
            Log::error('saveStyleFromUniqloStyleBook save error', [
                'styleId' => $styleId,
                'result' => $result,
            ]);

            report($e);
        }

        try {
            $items = collect(data_get($result, 'coordinates.*.items.*'));
            $productCodes = $items->map(function ($item) {
                $url = $item->item_detail_url;
                parse_str(parse_url($url, PHP_URL_QUERY), $urlQueries);

                $productCode = $urlQueries['productCode'] ?? null;

                if (is_null($productCode)) {
                    preg_match('/(u[0-9]+)/', $item->img_url_pc, $matches);
                    $productCode = $matches[1] ?? null;
                }

                if (is_null($productCode)) {
                    Log::error('saveStyleFromUniqloStyleBook productCode not found', [
                        'item' => $item,
                    ]);
                }

                return $productCode;
            })->filter()->all();

            /** @var HmallProductRepository */
            $hmallProductRepository = app(HmallProductRepository::class);
            $hmallProductIds = $hmallProductRepository->getIdsFromProductCodes($productCodes);

            $model->hmallProducts()->syncWithoutDetaching($hmallProductIds);
        } catch (Throwable $e) {
            Log::error('saveStyleFromUniqloStyleBook sync error', [
                'styleId' => $styleId,
                'result' => $result,
            ]);

            report($e);
        }
    }
}
