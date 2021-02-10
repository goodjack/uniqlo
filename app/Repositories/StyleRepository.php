<?php

namespace App\Repositories;

use App\Models\Style;
use Illuminate\Support\Facades\DB;
use Yish\Generators\Foundation\Repository\Repository;

class StyleRepository
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
}
