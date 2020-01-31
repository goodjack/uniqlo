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
                $html .= $this->getItemImageTag($product->id, $color->code, $colorHeader);
            }
        }

        return $html;
    }

    public function getItemImageTag($id, $colorCode, $colorHeader)
    {
        $imgUrl = "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$colorCode}_{$id}_popup.jpg";
        $largeImgUrl = "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$colorCode}_{$id}.jpg";

        // TODO: 整合至 getImageTag
        $html = "<a class=\"ts card\" href=\"{$largeImgUrl}\" data-lightbox=\"image\" data-title=\"{$colorHeader}\"><div class=\"image\"><img src=\"{$imgUrl}\" alt=\"{$colorHeader}\"></div><div class=\"overlapped content color-header\">{$colorHeader}</div></a>";

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
        $imgUrl = "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/sub/{$subImage}_popup.jpg";
        $largeImgUrl = "https://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/sub/{$subImage}.jpg";
        $link = $largeImgUrl;

        return $this->getImageTag($link, $imgUrl, $largeImgUrl);
    }

    public function getStyleDictionaries($styleDictionaries)
    {
        $html = '';
        foreach ($styleDictionaries as $styleDictionary) {
            $largeImgUrl = "https://www.uniqlo.com/tw/styledictionary/api/data/0/{$styleDictionary->fnm}-xxl.jpg";
            $html .= $this->getImageTag($largeImgUrl, $styleDictionary->image_url, $largeImgUrl);
        }

        return $html;
    }

    public function getStyles($styles)
    {
        $html = '';
        foreach ($styles as $style) {
            // TODO: 大圖存 DB
            $largeImgUrl = "https://im.uniqlo.com/style/{$style->image_path}-xxl.jpg";
            $html .= $this->getImageTag($style->detail_url, $style->image_url, $largeImgUrl);
        }

        return $html;
    }

    public function getImageTag($link, $imgUrl, $largeImgUrl = null)
    {
        // TODO: Image ALT Attributes
        // TODO: 出處說明文字

        if (empty($largeImgUrl)) {
            $largeImgUrl = $imgUrl;
        }

        return "<a class=\"ts card\" href=\"{$largeImgUrl}\" data-lightbox=\"image\" data-title=\"<a href='{$link}' target='_blank'>出處</a>\"><div class=\"image\"><img src=\"{$imgUrl}\"></div></a>";
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
