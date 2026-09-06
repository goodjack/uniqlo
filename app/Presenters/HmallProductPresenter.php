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

    /*
     * 標籤顏色。master 是一色一義（期間限定紅、特價與歷史新低同一個藍、新款綠……），
     * 不是「價格類一色、其餘灰」的兩色系統：同色代表同一件事，改色等於改語意。
     *
     * 藍色是唯一的例外：master 的 #00ADEA 放在白底上對比只有 2.4:1，13px 的字讀不清，
     * 所以白底的字改用加深版的 --uq-info-text（#0087B8，對比 4.6:1）。GU 角標那種
     * 實心底色維持 #00ADEA，那是底色不是字色，沒有對比問題。
     */
    private const COLOR_OFFER = '#CE5F58';

    private const COLOR_PRICE = 'var(--uq-info-text)';

    private const COLOR_NEW = '#8BB96E';

    private const COLOR_COMING_SOON = '#50723C';

    private const COLOR_MULTI_BUY = '#79A8B9';

    /*
     * 網路獨家在 master 是兩個值：卡片是 #F29E18（跟清單頁的 icon、商品頁那顆
     * online-special 按鈕同色），商品頁的標籤是 #79A8B9（跟合購同色）。兩邊都照
     * 各自的原樣還原，不併成一色——併色是再設計。
     */
    private const COLOR_ONLINE_SPECIAL_CARD = '#F29E18';

    private const COLOR_ONLINE_SPECIAL_PRODUCT = '#79A8B9';

    private const COLOR_NEUTRAL = '#5A5A5A';

    private const COLOR_TOP_WEARING = '#CC7F49';

    private const COLOR_MOST_VISITED = '#B58105';

    /**
     * 商品的狀態標籤，依「使用者最在意什麼」排序：省多少錢 › 買不買得到 › 其他屬性。
     *
     * 卡片與商品頁共用這一份順序與文案，同一件商品在列表與內頁強調的才會是同一
     * 件事。差別只有最後那六個屬性標籤（豐富尺碼、男女適穿、旗艦店款、大型店商品、
     * 特定店商品、修改褲長）：$forProductPage 為真才給。那六個講的是這件商品怎麼買、
     * 怎麼改，是決定要不要買的時候才要看的細節，master 也只有商品頁列。
     *
     * 每一筆：
     *   text   顯示文字
     *   color  文字色，直接寫進 style。哪一色代表什麼見上面的顏色常數
     *   icon   Tocas 的 icon class。商品頁畫在文字前面，卡片不畫
     *   url    有對應清單頁時給。商品頁據此做成連結，點得回那個清單
     *   title  文字被縮短過時的完整說明（穿搭 TOP 51 以後只寫「熱門穿搭」）
     *
     * @return array<int, array{text: string, color: string, icon: string, url: string|null, title: string|null}>
     */
    public function getProductTags($hmallProduct, bool $forProductPage = false): array
    {
        $tags = [];

        $add = function (
            string $text,
            string $color,
            string $icon,
            ?string $url = null,
            ?string $title = null
        ) use (&$tags) {
            $tags[] = ['text' => $text, 'color' => $color, 'icon' => $icon, 'url' => $url, 'title' => $title];
        };

        if ($hmallProduct->is_new_historical_low) {
            $add('歷史新低價', self::COLOR_PRICE, 'arrow down');
        }

        if ($hmallProduct->is_limited_offer) {
            // 截止日寫在標籤上，master 的卡片就是這樣：在列表上看得到哪天結束，
            // 才決定得了要不要現在買。沒有檔期日期時退回「期間限定特價」
            $add(
                $this->getLimitedOfferMessage($hmallProduct),
                self::COLOR_OFFER,
                'certificate',
                route('lists.limited-offers')
            );
        }

        if ($hmallProduct->is_sale) {
            $add('特價商品', self::COLOR_PRICE, 'shopping basket', route('lists.sale'));
        }

        if ($hmallProduct->is_new) {
            $add('新款商品', self::COLOR_NEW, 'leaf', route('lists.new'));
        }

        if ($hmallProduct->is_coming_soon) {
            $add('即將上市', self::COLOR_COMING_SOON, 'checked calendar', route('lists.coming-soon'));
        }

        if ($hmallProduct->is_multi_buy) {
            $add('合購商品', self::COLOR_MULTI_BUY, 'cubes', route('lists.multi-buy'));
        }

        if ($hmallProduct->is_online_special) {
            $color = $forProductPage ? self::COLOR_ONLINE_SPECIAL_PRODUCT : self::COLOR_ONLINE_SPECIAL_CARD;
            $add('網路獨家販售', $color, 'tv', route('lists.online-special'));
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
            $rank = $hmallProduct->top_wearing_rank;
            $add(
                $rank <= 50 ? "穿搭 TOP {$rank}" : '熱門穿搭',
                self::COLOR_TOP_WEARING,
                'camera retro',
                route('lists.top-wearing'),
                "穿搭 TOP {$rank}"
            );
        }

        if ($hmallProduct->most_visited_rank) {
            $rank = $hmallProduct->most_visited_rank;
            $add(
                $rank <= 50 ? "瀏覽 TOP {$rank}" : '熱門瀏覽',
                self::COLOR_MOST_VISITED,
                'chart line',
                route('lists.most-visited'),
                "瀏覽 TOP {$rank}"
            );
        }

        if (! $forProductPage) {
            return $tags;
        }

        // 尺碼與通路這些屬性沒有對應的清單頁，也沒有輕重之分，一起排在最後
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

        // 沒有任何價格歷史就沒有尾段可以畫。正常情況下爬蟲建檔時就會寫一筆，
        // 但少了它不該讓整個商品頁 500。
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
