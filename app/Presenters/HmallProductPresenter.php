<?php

namespace App\Presenters;

use App\Enums\ProductTag;
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

    /*
     * 標籤文字色，一色一義：同色代表同一件事，改色等於改語意。
     * 文字用 app.css 裡加深過的 --uq-*-text（白底要達 4.5:1），icon 與實心底
     * 角標維持原色。
     */
    private const COLOR_OFFER = 'var(--uq-offer-text)';

    private const COLOR_PRICE = 'var(--uq-info-text)';

    private const COLOR_NEW = 'var(--uq-new-text)';

    private const COLOR_COMING_SOON = '#50723C';

    private const COLOR_MULTI_BUY = 'var(--uq-multi-buy-text)';

    private const COLOR_ONLINE_SPECIAL = 'var(--uq-online-special-text)';

    private const COLOR_NEUTRAL = '#5A5A5A';

    private const COLOR_TOP_WEARING = 'var(--uq-top-wearing-text)';

    private const COLOR_MOST_VISITED = 'var(--uq-most-visited-text)';

    /**
     * 商品的狀態標籤，依使用者在意的程度排序：省多少錢 › 買不買得到 › 其他屬性。
     * 卡片與商品頁共用同一份順序與文案；尺碼、通路這類屬性只在商品頁給。
     *
     * @return array<int, array{text: string, color: string, icon: string, url: string|null}>
     */
    public function getProductTags($hmallProduct, bool $forProductPage = false): array
    {
        $tags = [];

        $add = function (string $text, string $color, string $icon, ?string $url = null) use (&$tags) {
            $tags[] = ['text' => $text, 'color' => $color, 'icon' => $icon, 'url' => $url];
        };

        if ($hmallProduct->is_new_historical_low) {
            $add('歷史新低價', self::COLOR_PRICE, 'arrow down');
        }

        if ($hmallProduct->is_limited_offer) {
            $add($this->getLimitedOfferMessage($hmallProduct), self::COLOR_OFFER, 'certificate', route('lists.limited-offers'));
        }

        // 文案刻意跟 ProductTag::label()（篩選用的短名）不同，卡片要的是完整說法
        if ($hmallProduct->is_sale) {
            $add('特價商品', self::COLOR_PRICE, 'shopping basket', route('lists.sale'));
        }

        if ($hmallProduct->is_new) {
            $add('新款商品', self::COLOR_NEW, 'leaf', route('lists.new'));
        }

        if ($hmallProduct->is_coming_soon) {
            $add(ProductTag::ComingSoon->label(), self::COLOR_COMING_SOON, 'checked calendar', route('lists.coming-soon'));
        }

        if ($hmallProduct->is_multi_buy) {
            $add('合購商品', self::COLOR_MULTI_BUY, 'cubes', route('lists.multi-buy'));
        }

        if ($hmallProduct->is_online_special) {
            $add('網路獨家販售', self::COLOR_ONLINE_SPECIAL, 'tv', route('lists.online-special'));
        }

        if ($hmallProduct->is_app_offer) {
            $add('APP 限定特價', self::COLOR_OFFER, 'certificate', route('lists.limited-offers'));
        }

        if ($hmallProduct->is_ec_only) {
            $add('網路限定特價', self::COLOR_OFFER, 'certificate', route('lists.limited-offers'));
        }

        if ($hmallProduct->is_stockout) {
            $add('已售罄', self::COLOR_NEUTRAL, 'archive');
        }

        if ($hmallProduct->top_wearing_rank) {
            $add("穿搭 TOP {$hmallProduct->top_wearing_rank}", self::COLOR_TOP_WEARING, 'camera retro', route('lists.top-wearing'));
        }

        if ($hmallProduct->most_visited_rank) {
            $add("瀏覽 TOP {$hmallProduct->most_visited_rank}", self::COLOR_MOST_VISITED, 'chart line', route('lists.most-visited'));
        }

        if (! $forProductPage) {
            return $tags;
        }

        $attributes = [
            'is_extended_size' => ['豐富尺碼', 'external square'],
            'is_unisex' => ['男女適穿', 'venus mars'],
            'is_super_large' => ['旗艦店款', 'diamond'],
            'is_ec_big' => ['大型店商品', 'diamond'],
            'is_ec_selected' => ['特定店商品', 'diamond'],
            'is_revision' => ['修改褲長', 'cut'],
        ];

        foreach ($attributes as $attribute => [$text, $icon]) {
            if ($hmallProduct->{$attribute}) {
                $add($text, self::COLOR_NEUTRAL, $icon);
            }
        }

        return $tags;
    }

    /**
     * 「優惠中」＝現在買比較划算，而且買得到（收藏頁的「只看優惠中」）。
     * 新品、排行、網路獨家講的不是划不划算，不算。
     */
    public function isOnOffer($hmallProduct): bool
    {
        if ($hmallProduct->is_stockout) {
            return false;
        }

        return $hmallProduct->is_limited_offer
            || $hmallProduct->is_ec_only
            || $hmallProduct->is_new_historical_low
            || $hmallProduct->is_multi_buy
            || $hmallProduct->is_sale
            || $hmallProduct->is_app_offer;
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

        if ($lastHmallPriceHistories === null) {
            return null;
        }

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
