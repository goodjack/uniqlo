<?php

namespace App\Enums;

use App\Models\HmallProduct;

/**
 * 商品標籤，用於清單頁與分類頁的篩選。
 *
 * 兩種頁面的資料來源不同——清單頁是預熱好的 Collection、分類頁是資料庫查詢加
 * 分頁——所以判準必須能用在兩邊。定義寫在同一個地方，避免記憶體版與 SQL 版
 * 各自演化。分類頁尤其不能先取一頁再過濾，那會漏商品也會讓頁數不對。
 */
enum ProductTag: string
{
    case LowestPrice = 'lowest-price';

    /**
     * 期間限定：判準跟限時特價清單頁完全一樣（落在檔期內，或官方標了
     * time_doptimal）。兩邊用同一組條件，頁面上的商品才必然帶得到這個標籤。
     */
    case LimitedOffer = 'limited-offer';
    case Sale = 'sale';
    case NewArrival = 'new';
    case ComingSoon = 'coming-soon';
    case MultiBuy = 'multi-buy';
    case OnlineSpecial = 'online-special';

    public function label(): string
    {
        return match ($this) {
            self::LowestPrice => '目前史上最低',
            self::LimitedOffer => '期間限定',
            self::Sale => '特價',
            self::NewArrival => '新品',
            self::ComingSoon => '即將上市',
            self::MultiBuy => '合購',
            self::OnlineSpecial => '網路獨家',
        };
    }

    /**
     * 這個標籤在 identity 裡對應的官方代碼。任一命中就算符合。
     *
     * @return array<int, string>
     */
    private function identityCodes(): array
    {
        return match ($this) {
            self::LimitedOffer => ['time_doptimal'],
            self::Sale => ['concessional_rate'],
            self::NewArrival => ['new_product'],
            self::ComingSoon => ['COMING SOON', 'COMING'],
            self::MultiBuy => ['multi_buy', 'SET'],
            self::OnlineSpecial => ['ONLINE SPECIAL', 'ECONLY'],
            self::LowestPrice => [],
        };
    }

    public function matches(HmallProduct $product): bool
    {
        if ($this === self::LowestPrice) {
            return $product->is_at_lowest_price;
        }

        if ($this === self::LimitedOffer && $this->isWithinLimitedOfferPeriod($product)) {
            return true;
        }

        $identity = json_decode($product->identity ?? '[]') ?: [];

        foreach ($this->identityCodes() as $code) {
            if (in_array($code, $identity, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 把這個標籤加成查詢條件（會加在呼叫端的 orWhere 群組裡）。
     *
     * 不標型別是因為呼叫端傳進來的可能是 Eloquent 或 Query 的 builder，
     * 兩者沒有共同的介面可以標。
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public function applyTo($query): void
    {
        if ($this === self::LowestPrice) {
            $query->orWhere(function ($lowest) {
                $lowest->whereColumn('hmall_products.min_price', 'hmall_products.lowest_record_price')
                    ->whereColumn('hmall_products.lowest_record_price', '<', 'hmall_products.highest_record_price');
            });

            return;
        }

        if ($this === self::LimitedOffer) {
            // 時間綁成一個值傳下去，SQL 與 matches() 才是同一個時刻
            $now = now();

            $query->orWhere(function ($period) use ($now) {
                $period->where('hmall_products.time_limited_begin', '<=', $now)
                    ->where('hmall_products.time_limited_end', '>=', $now);
            });
        }

        foreach ($this->identityCodes() as $code) {
            // identity 是 json 欄位，用 JSON_CONTAINS 比整個陣列元素。
            // 之前用 LIKE 湊引號能擋掉大部分誤命中，但那是拿字串比對假裝成
            // 結構比對；真實資料上 ECONLY 就被寫成 LIKE '%ECONLY%' 而誤收了
            // 14 件 ECONLYAD。
            $query->orWhereJsonContains('hmall_products.identity', $code);
        }
    }

    /**
     * 現在正落在官方的限時特價檔期裡。
     *
     * 兩端都要有值：只有開始或只有結束不構成一段檔期，SQL 那邊的 NULL 比較
     * 也是不成立，兩邊要一致。
     */
    private function isWithinLimitedOfferPeriod(HmallProduct $product): bool
    {
        $begin = $product->time_limited_begin;
        $end = $product->time_limited_end;

        if ($begin === null || $end === null) {
            return false;
        }

        $now = now();

        return $begin <= $now && $end >= $now;
    }

    /**
     * @return array<int, self>
     */
    public static function fromValues(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => is_string($value) ? self::tryFrom($value) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
