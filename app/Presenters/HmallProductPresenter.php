<?php

namespace App\Presenters;

use App\Models\HmallPriceHistory;
use App\Models\HmallProduct;

class HmallProductPresenter
{
    public function getNameWithCode($hmallProduct): string
    {
        return "{$hmallProduct->name} {$hmallProduct->code}";
    }

    public function getFullName($hmallProduct)
    {
        return "{$hmallProduct->sex} {$hmallProduct->name}";
    }

    public function getFullNameWithCode($hmallProduct)
    {
        $fullName = $this->getFullName($hmallProduct);

        return "{$fullName} {$hmallProduct->code}";
    }

    public function getFullNameWithCodeAndProductCode($hmallProduct)
    {
        $fullNameWithCode = $this->getFullNameWithCode($hmallProduct);

        return "{$fullNameWithCode} {$hmallProduct->product_code}";
    }

    public function getDescription($hmallProduct)
    {
        $description = $hmallProduct->instruction;

        // 去除 GU 產品標示
        $description = preg_replace('/<div class="desc-item">\X*<\/div>/U', '', $description);

        // 去除特殊標示
        $description = preg_replace('/<span class">\X*<\/span>/U', '', $description);

        // 去除 HTML br 以外的 tag
        $description = strip_tags($description, '<br>');

        // 整理中間換行
        $description = preg_replace('/\n/', '', $description);
        $description = preg_replace('/<br\s*\/?>/', '<br>', $description);
        $description = preg_replace('/(<br>){3,}/', '<br><br>', $description);
        $description = preg_replace('/※.*(<br>|$)/U', '', $description);

        // 去除 UNIQLO 產品標示
        $description = preg_replace('/(商品材質|物料組成|網路商店退貨須知)\X*/', '', $description);

        // 去除 GU 產品標示
        $description = preg_replace('/(商品產地)\X*/', '', $description);

        // 整理前後換行
        $description = trim($description);
        $description = preg_replace('/^(<br>)+|(<br>)+$/', '', $description);

        $description .= "<br><br>網路商店編號：{$hmallProduct->product_code}";

        return $description;
    }

    public function getMainFirstPic($hmallProduct)
    {
        if ($hmallProduct->brand === 'GU') {
            return "https://www.gu-global.com/tw{$hmallProduct->main_first_pic}";
        }

        return "https://www.uniqlo.com/tw{$hmallProduct->main_first_pic}";
    }

    public function getSkuPic($hmallProduct, $colorNum)
    {
        if ($hmallProduct->brand === 'GU') {
            return "https://www.gu-global.com/tw/hmall/test/{$hmallProduct->product_code}/sku/561/{$colorNum}.jpg";
        }

        return "https://www.uniqlo.com/tw/hmall/test/{$hmallProduct->product_code}/sku/561/{$colorNum}.jpg";
    }

    public function getStyleHintsRoute(HmallProduct $hmallProduct): string
    {
        if ($hmallProduct->brand === 'GU') {
            return route('gu-style-hints.show', ['gu_product_code' => $hmallProduct->product_code]);
        }

        return route('uniqlo-style-hints.show', ['uniqlo_product_code' => $hmallProduct->product_code]);
    }

    public function getHmallProductTag($hmallProduct)
    {
        $html = '';

        if ($hmallProduct->is_limited_offer || $hmallProduct->is_app_offer || $hmallProduct->is_ec_only) {
            $message = $this->getLimitedOfferMessage($hmallProduct);
            $route = route('lists.limited-offers');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #CE5F58;"><i class="certificate icon"></i>';
            $html .= $message;
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_app_offer) {
            $route = route('lists.limited-offers');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #CE5F58;"><i class="certificate icon"></i>';
            $html .= 'APP 限定特價';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_ec_only) {
            $route = route('lists.limited-offers');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #CE5F58;"><i class="certificate icon"></i>';
            $html .= '網路限定特價';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_sale) {
            $route = route('lists.sale');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #00ADEA;"><i class="shopping basket icon"></i>';
            $html .= '特價商品';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_new_historical_low) {
            $html .= '<a class="ts horizontal basic circular label"><span style="color: #00ADEA;"><i class="arrow down icon"></i>';
            $html .= '歷史新低價';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_new) {
            $route = route('lists.new');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #8BB96E;"><i class="leaf icon"></i>';
            $html .= '新款商品';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_coming_soon) {
            $route = route('lists.coming-soon');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #50723C;"><i class="checked calendar icon"></i>';
            $html .= '即將上市';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_multi_buy) {
            $route = route('lists.multi-buy');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #79A8B9;"><i class="cubes icon"></i>';
            $html .= '合購商品';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_online_special) {
            $route = route('lists.online-special');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #79A8B9;"><i class="tv icon"></i>';
            $html .= '網路獨家販售';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_stockout) {
            $html .= '<a class="ts horizontal basic circular label"><span style="color: #5A5A5A;"><i class="archive icon"></i>';
            $html .= '已售罄';
            $html .= '</span></a>';
        }

        if ($hmallProduct->top_wearing_rank) {
            $route = route('lists.top-wearing');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #CC7F49;"><i class="camera retro icon"></i>';
            $html .= "穿搭 TOP {$hmallProduct->top_wearing_rank}";
            $html .= '</span></a>';
        }

        if ($hmallProduct->most_visited_rank) {
            $route = route('lists.most-visited');

            $html .= "<a href={$route} ";
            $html .= 'class="ts horizontal basic circular label"><span style="color: #B58105;"><i class="chart line icon"></i>';
            $html .= "瀏覽 TOP {$hmallProduct->most_visited_rank}";
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_extended_size) {
            $html .= '<a class="ts horizontal basic circular label"><span style="color: #5A5A5A;"><i class="external square icon"></i>';
            $html .= '豐富尺碼';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_unisex) {
            $html .= '<a class="ts horizontal basic circular label"><span style="color: #5A5A5A;"><i class="venus mars icon"></i>';
            $html .= '男女適穿';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_super_large) {
            $html .= '<a class="ts horizontal basic circular label"><span style="color: #5A5A5A;"><i class="diamond icon"></i>';
            $html .= '旗艦店款';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_ec_big) {
            $html .= '<a class="ts horizontal basic circular label"><span style="color: #5A5A5A;"><i class="diamond icon"></i>';
            $html .= '大型店商品';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_ec_selected) {
            $html .= '<a class="ts horizontal basic circular label"><span style="color: #5A5A5A;"><i class="diamond icon"></i>';
            $html .= '特定店商品';
            $html .= '</span></a>';
        }

        if ($hmallProduct->is_revision) {
            $html .= '<a class="ts horizontal basic circular label"><span style="color: #5A5A5A;"><i class="cut icon"></i>';
            $html .= '修改褲長';
            $html .= '</span></a>';
        }

        return $html;
    }

    public function getLimitedOfferMessage($hmallProduct)
    {
        $date = $hmallProduct->limited_offer_end_date;

        if (empty($date)) {
            return '期間限定特價';
        }

        $formattedDate = $date->format('m/d');

        return "截至 {$formattedDate} 限定價格";
    }

    public function getPriceChartData($hmallPriceHistories)
    {
        $data = $hmallPriceHistories->map(function (HmallPriceHistory $history) {
            return [
                't' => $history->created_at->toDateString(),
                'y' => $history->min_price,
            ];
        });

        $lastData = $this->getLastPriceChartData($hmallPriceHistories);

        if ($lastData) {
            $data[] = $lastData;
        }

        return json_encode($data);
    }

    public function getDescriptionForJsonLd($hmallProduct)
    {
        $description = $this->getSocialMediaDescription($hmallProduct);

        return json_encode($description);
    }

    public function getProductAvailabilityForJsonLd($hmallProduct)
    {
        if ($hmallProduct->is_stockout) {
            return 'https://schema.org/OutOfStock';
        }

        return 'https://schema.org/InStock';
    }

    public function getRatingForProductShow($hmallProduct)
    {
        $html = $this->getRating($hmallProduct);

        if (! empty($html)) {
            $html = "&middot; {$html}";
        }

        return $html;
    }

    public function getRatingForProductCardAndItem($hmallProduct, $useJapanRating = false)
    {
        $html = $useJapanRating ? $this->getJapanRating($hmallProduct) : $this->getRating($hmallProduct);

        if (! empty($html)) {
            $html = "<span>{$html}</span>";
        }

        return $html;
    }

    public function getVideoIconForProductCardAndItem($hmallProduct)
    {
        $hasVideos = optional($hmallProduct->japanProduct)->has_videos;

        if ($hasVideos) {
            return '<span><i class="video camera icon"></i></span>';
        }

        return '';
    }

    public function getSocialMediaDescription($hmallProduct)
    {
        $description = $this->getDescription($hmallProduct);
        $description = strip_tags($description);
        $description = trim(preg_replace('/\s+/', ' ', $description));

        $description = "{$description} | UNIQLO 比價 | UQ 搜尋";

        $rating = $this->getRating($hmallProduct, true);

        if (! empty($rating)) {
            $description = "{$rating} · {$description}";
        }

        return $description;
    }

    public function getRating($hmallProduct, $plainText = false)
    {
        if (empty($hmallProduct->evaluation_count)) {
            return '';
        }

        $rating = $plainText ? '★ ' : '<i class="fitted star icon"></i> ';

        $rating .= number_format($hmallProduct->score, 1);

        $rating .= " ({$hmallProduct->evaluation_count})";

        return $rating;
    }

    public function getJapanRating($hmallProduct, $plainText = false)
    {
        if (empty(optional($hmallProduct->japanProduct)->rating_count)) {
            return '';
        }

        $rating = $plainText ? '★ ' : '<i class="fitted star icon"></i> ';

        $rating .= number_format($hmallProduct->japanProduct->rating_average, 1);

        $rating .= " ({$hmallProduct->japanProduct->rating_count})";

        return $rating;
    }

    private function getLastPriceChartData($hmallPriceHistories)
    {
        $lastHmallPriceHistories = $hmallPriceHistories->last();

        /** @var \Carbon\Carbon $lastHistoryAt */
        $lastHistoryAt = $lastHmallPriceHistories->created_at;

        /** @var \Carbon\Carbon $lastAvailableAt */
        $lastAvailableAt = $lastHmallPriceHistories->hmallProduct->last_available_at;

        if ($lastAvailableAt->lessThanOrEqualTo($lastHistoryAt)) {
            return null;
        }

        return [
            't' => $lastAvailableAt->toDateString(),
            'y' => $lastHmallPriceHistories->min_price,
        ];
    }
}
