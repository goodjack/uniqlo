<?php

namespace App\Presenters;

use App\Models\Product;
use App\Support\SiteTitle;
use Illuminate\Support\Collection;

class ProductPresenter
{
    public function getProductMainImageUrl($product, Collection $hmallProducts)
    {
        if ($hmallProducts->isEmpty()) {
            return $product->main_image_url;
        }

        $relatedId = substr($product->id, 0, 6);
        $hmallProduct = $hmallProducts->firstWhere('code', $relatedId);

        if (empty($hmallProduct)) {
            return $product->main_image_url;
        }

        $hmallProductPresenter = app(HmallProductPresenter::class);

        return $hmallProductPresenter->getMainFirstPic($hmallProduct);
    }

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
            $html = '<div class="ts separated buttons">'.$html;
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * 商品實照：先放實照、再放各顏色的圖，交給 x-image-card 畫。
     *
     * @return array<int, array{link: ?string, imageUrl: ?string, largeImageUrl: string, alt: string, colorHeader: ?string}>
     */
    public function getPhotoCards($product): array
    {
        $base = "https://im.uniqlo.com/images/tw/uq/pc/goods/{$product->id}";
        $cards = [];

        foreach (json_decode($product->sub_images) ?: [] as $key => $subImage) {
            $cards[] = $this->card(null, "{$base}/sub/{$subImage}_popup.jpg", "{$base}/sub/{$subImage}.jpg", '商品實照 '.($key + 1));
        }

        foreach (json_decode($product->colors) ?: [] as $color) {
            $colorHeader = "{$color->code} {$color->name}";
            $cards[] = $this->card(
                null,
                "{$base}/item/{$color->code}_{$product->id}_popup.jpg",
                "{$base}/item/{$color->code}_{$product->id}.jpg",
                "商品顏色 {$colorHeader}",
                $colorHeader,
            );
        }

        return $cards;
    }

    /**
     * 官方精選穿搭：Style 在前、Style Dictionary 在後，交給 x-image-card 畫。
     *
     * @return array<int, array{link: ?string, imageUrl: ?string, largeImageUrl: string, alt: string, colorHeader: ?string}>
     */
    public function getStyleCards($styles, $styleDictionaries): array
    {
        $cards = [];

        foreach ($styles as $key => $style) {
            $cards[] = $this->card(
                $style->detail_url,
                $style->image_url,
                $style->large_image_url,
                '精選穿搭 Style '.($key + 1),
            );
        }

        foreach ($styleDictionaries as $key => $styleDictionary) {
            $cards[] = $this->card(
                null,
                $styleDictionary->image_url,
                "https://www.uniqlo.com//tw/styledictionary/api/data/0/{$styleDictionary->fnm}-xxl.jpg",
                '精選穿搭 Style Dictionary '.($key + 1),
            );
        }

        return $cards;
    }

    private function card(?string $link, ?string $imageUrl, string $largeImageUrl, string $alt, ?string $colorHeader = null): array
    {
        return compact('link', 'imageUrl', 'largeImageUrl', 'alt', 'colorHeader');
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
     * @param  Product  $product
     * @return void
     */
    public function getProductUrl($product)
    {
        return action('ProductController@show', ['product' => $product->id]);
    }

    public function getProductAvailabilityForJsonLd($product)
    {
        if ($product->stockout) {
            return 'https://schema.org/OutOfStock';
        }

        return 'https://schema.org/InStock';
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
        $description = strip_tags($product->comment);
        $description = trim(preg_replace('/\s+/', ' ', $description));

        $description = SiteTitle::appendWithTagline($description);

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
