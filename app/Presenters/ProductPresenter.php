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
            $route = route('products.limited-offers');
            $html .= "<a href=\"{$route}\" class=\"ts circular mini very compact negative button\"><i class=\"certificate icon\"></i>{$msg}</a>";
        }

        if ($product->multi_buy) {
            $route = route('products.multi-buys');
            $html .= "<a href=\"{$route}\" class=\"ts circular mini very compact info button\"><i class=\"cubes icon\"></i>{$product->multi_buy}</a>";
        }

        if ($product->new) {
            $route = route('products.news');
            $html .= "<a href=\"{$route}\" class=\"ts circular mini very compact positive button\"><i class=\"leaf icon\"></i>新品</a>";
        }

        if ($product->sale) {
            $route = route('products.sales');
            $html .= "<a href=\"{$route}\" class=\"ts circular mini very compact primary button\"><i class=\"shopping basket icon\"></i>特價商品</a>";
        }

        if ($product->stockout) {
            $route = route('products.stockouts');
            $html .= "<a href=\"{$route}\" class=\"ts circular mini very compact button\"><i class=\"archive icon\"></i>可能已經沒有庫存</a>";
        }

        if ($html) {
            $html = '<div class="ts separated buttons">' . $html;
            $html .= "</div><div class=\"ts hidden divider\"></div>";
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
            $largeImgUrl = "https://im.uniqlo.com/style/{$style->image_path}-xxxl.jpg";
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

    public function getRatingForProductShow($product)
    {
        $html = $this->getRating($product);

        if (! empty($html)) {
            $html = "&middot; {$html}";
        }

        return $html;
    }

    public function getRatingForProductCardAndItem($product)
    {
        $html = $this->getRating($product);

        if (! empty($html)) {
            $html = "<span>{$html}</span>";
        }

        return $html;
    }

    public function getSocialMediaDescription($product)
    {
        $description = "{$product->comment} | UNIQLO 比價 | UQ 搜尋";

        $rating = $this->getRating($product, true);

        if (! empty($rating)) {
            $description = "{$rating} · {$description}";
        }

        return $description;
    }

    public function getRating($product, $plainText = false)
    {
        if (empty($product->review_count)) {
            return '';
        }

        $rating = $plainText ? '★ ' : '<i class="fitted star icon"></i> ';

        if ($product->review_rating) {
            $rating .= number_format($product->review_rating, 1, '.', '');
        } else {
            $rating .= '?';
        }

        $rating .= " ({$product->review_count})";

        return $rating;
    }

    public function countProducts($products)
    {
        return count($products['men'])
            + count($products['women'])
            + count($products['kids'])
            + count($products['baby']);
    }
}
