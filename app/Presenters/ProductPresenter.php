<?php

namespace App\Presenters;

use App\Product;

class ProductPresenter
{
    public function getProductTag($product)
    {
        $html = '';

        $msg = $product->limit_sales_end_msg;
        if ($msg) {
            $html .= "<div class=\"ts horizontal negative circular label\">{$msg}</div>";
        }

        if ($product->multi_buy) {
            $html .= "<div class=\"ts horizontal negative circular label\">{$product->multi_buy}</div>";
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
                $alt = "商品顏色 {$colorHeader}";

                $html .= $this->getItemImageTag($product->id, $color->code, $colorHeader, $alt);
            }
        }

        return $html;
    }

    public function getItemImageTag($id, $colorCode, $colorHeader, $alt)
    {
        $imgUrl = "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$colorCode}_{$id}_popup.jpg";
        $largeImgUrl = "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$colorCode}_{$id}.jpg";
        $link = $largeImgUrl;

        $html = $this->getImageTag($link, $imgUrl, $largeImgUrl, $alt, $colorHeader);

        return $html;
    }

    public function getSubImages($product)
    {
        $html = '';
        $subImages = json_decode($product->sub_images);
        foreach ($subImages as $key => $subImage) {
            $alt = '商品實照 ' . ($key + 1);

            $html .= $this->getSubImageTag($product->id, $subImage, $alt);
        }

        return $html;
    }

    public function getSubImageTag($id, $subImage, $alt)
    {
        $imgUrl = "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/sub/{$subImage}_popup.jpg";
        $largeImgUrl = "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/sub/{$subImage}.jpg";
        $link = $largeImgUrl;

        return $this->getImageTag($link, $imgUrl, $largeImgUrl, $alt);
    }

    public function getStyleDictionaries($styleDictionaries)
    {
        $html = '';
        foreach ($styleDictionaries as $key => $styleDictionary) {
            $largeImgUrl = "https://www.uniqlo.com//tw/styledictionary/api/data/0/{$styleDictionary->fnm}-xxl.jpg";
            $alt = '精選穿搭 Style Dictionary ' . ($key + 1);

            $html .= $this->getImageTag($largeImgUrl, $styleDictionary->image_url, $largeImgUrl, $alt);
        }

        return $html;
    }

    public function getStyles($styles)
    {
        $html = '';
        foreach ($styles as $key => $style) {
            // TODO: 大圖存 DB
            $largeImgUrl = "https://im.uniqlo.com/style/{$style->image_path}-xxl.jpg";
            $alt = '精選穿搭 Style ' . ($key + 1);

            $html .= $this->getImageTag($style->detail_url, $style->image_url, $largeImgUrl, $alt);
        }

        return $html;
    }

    public function getImageTag($link, $imgUrl, $largeImgUrl, $alt, $colorHeader = null)
    {
        $html = "<a class=\"ts card\" href=\"{$largeImgUrl}\" rel=\"nofollow noopener\" data-lightbox=\"image\" data-title=\"<a href='{$link}' target='_blank' rel='nofollow noopener'>{$alt}</a>\"><div class=\"image\"><img data-src=\"{$imgUrl}\" class=\"lazyload\" loading=\"lazy\" alt=\"{$alt}\"></div>";

        if (isset($colorHeader)) {
            $html .= "<div class=\"overlapped content color-header\">{$colorHeader}</div>";
        }

        $html .= "</a>";

        return $html;
    }

    public function getPriceChartLabels($productHistories)
    {
        return $productHistories->pluck(['created_at']);
    }

    public function getPriceChartData($productHistories)
    {
        return $productHistories->pluck(['price']);
    }

    public function getPriceChartMultiBuyData($productHistories)
    {
        return $productHistories->pluck(['multi_buy']);
    }

    /**
     * Get the URL of the specified product.
     *
     * @param Product $product
     * @return void
     */
    public function getProductUrl($product)
    {
        return action('ProductController@show', ['product' => $product->id]);
    }

    public function getProductAvailabilityForJsonLd($product)
    {
        if ($product->stockout) {
            return "https://schema.org/OutOfStock";
        }

        return "https://schema.org/InStock";
    }

    public function countProducts($products)
    {
        return count($products['men'])
            + count($products['women'])
            + count($products['kids'])
            + count($products['baby']);
    }
}
