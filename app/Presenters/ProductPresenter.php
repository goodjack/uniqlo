<?php

namespace App\Presenters;

use App\Product;
use Carbon\Carbon;

class ProductPresenter
{
    public function getProductTag($product)
    {
        $html = '';

        $msg = $product->limit_sales_end_msg;
        if ($msg) {
            $html .= "<div class=\"ts horizontal negative circular label\">{$msg}</div>";
        }

        if ($product->new) {
            $html .= "<div class=\"ts horizontal inverted circular label\">新品</div>";
        }

        if ($product->sale) {
            $html .= "<div class=\"ts horizontal inverted circular label\">特價商品</div>";
        }

        if ($product->stockout) {
            $html .= "<div class=\"ts horizontal circular label\">可能已經沒有庫存</div>";
        }

        if ($html) {
            $html .= "<div class=\"ts hidden divider\"></div>";
        }

        return $html;
    }

    public function getItemImages($product)
    {
        $html = '';
        $colors = json_decode($product->colors);

        if (!empty($colors)) {
            foreach ($colors as $color) {
                $colorHeader = "{$color->code} {$color->name}";
                $html .= $this->getItemImageTag($product->id, $color->code, $colorHeader);
            }
        }

        return $html;
    }

    public function getItemImageTag($id, $colorCode, $colorHeader)
    {
        $link = "http://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$colorCode}_{$id}.jpg";
        $imgUrl = $link;

        $html = "<a class=\"ts card\" href=\"{$link}\" target=\"_blank\"><div class=\"image\"><img src=\"{$imgUrl}\"></div><div class=\"overlapped content color-header\">{$colorHeader}</div></a>";

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

    /**
     * Get the URL of the specified product.
     *
     * @param Product $product
     * @return void
     */
    public function getProductUrl($product)
    {
        return action('ProductController@show', ['id' => $product->id]);
    }

    public function countProducts($products)
    {
        return count($products['men'])
            + count($products['women'])
            + count($products['kids'])
            + count($products['baby']);
    }
}
