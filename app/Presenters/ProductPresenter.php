<?php

namespace App\Presenters;

use Carbon\Carbon;

class ProductPresenter
{
    public function getProductTag($product)
    {
        $html = '';

        $msg = $product->limit_sales_end_msg;
        if ($msg) {
            $html .= "<div class=\"ts negative circular label\">{$msg}</div>";
        }

        $new = $product->new;
        if ($new) {
            $html .= "<div class=\"ts inverted circular label\">新品</div>";
        }

        return $html;
    }

    public function getSubImages($product)
    {
        $html = '';
        $subImages = json_decode($product->sub_images);
        foreach ($subImages as $subImage) {
            $html .= $this->getSubImageTag($product->id, $subImage);
        }

        return $html;
    }

    public function getSubImageTag($id, $subImage)
    {
        $link = "http://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/sub/{$subImage}.jpg";
        $imgUrl = $link;

        return $this->getImageTag($link, $imgUrl);
    }

    public function getStyleDictionaries($styleDictionaries)
    {
        $html = '';
        foreach ($styleDictionaries as $styleDictionary) {
            $html .= $this->getImageTag($styleDictionary->detail_url, $styleDictionary->image_url);
        }

        return $html;
    }

    public function getImageTag($link, $imgUrl)
    {
        return "<a class=\"ts card\" href=\"{$link}\" target=\"_blank\"><div class=\"image\"><img src=\"{$imgUrl}\"></div></a>";
    }

    public function getPriceChartLabels($productHistories)
    {
        return $productHistories->pluck(['created_at'])->map(function ($time) {
            return Carbon::parse($time)->format('m/d');
        });
    }

    public function getPriceChartData($productHistories)
    {
        return $productHistories->pluck(['price']);
    }
}
