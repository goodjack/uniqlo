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
            $html .= "<div class=\"ts negative label\">{$msg}</div>";
        }

        $new = $product->new;
        if ($new) {
            $html .= "<div class=\"ts inverted label\">新品</div>";
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

    public function getStyleDictionaryImages($styleDictionary)
    {
        $html = '';
        foreach ($styleDictionary->imgs as $img) {
            $html .= $this->getStyleDictionaryImageTag($styleDictionary->imgdir, $img->id, $img->fnm);
        }

        return $html;
    }

    public function getStyleDictionaryImageTag($imgDir, $imgID, $imgFnm)
    {
        $link = "http://www.uniqlo.com/tw/stylingbook/detail.html#/detail/{$imgID}";
        $imgUrl = "http://www.uniqlo.com/{$imgDir}{$imgFnm}-xl.jpg";

        return $this->getImageTag($link, $imgUrl);
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
