<?php

namespace App\Enums;

use App\Models\HmallProduct;

/**
 * 清單頁與分類頁的篩選標籤。清單頁篩記憶體裡的 Collection（matches()），
 * 分類頁要在 SQL 裡篩才分得對頁（applyTo()），兩套判準必須一起改。
 */
enum ProductTag: string
{
    case LowestPrice = 'lowest-price';

    /**
     * 判準跟限時特價清單頁相同：落在檔期內，或官方標了 time_doptimal。
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
     * identity 裡對應的官方代碼，任一命中就算。兩家命名不同，但代碼不是品牌
     * 獨佔的（ECONLY 兩家都在用），所以一律全部比對、不看品牌。
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
     * 把這個標籤加成查詢條件，加在呼叫端的 orWhere 群組裡。
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
            // 比對整個陣列元素；LIKE 會讓 ECONLY 誤中 ECONLYAD
            $query->orWhereJsonContains('hmall_products.identity', $code);
        }
    }

    /**
     * 兩端都要有值才算一段檔期，跟 SQL 的 NULL 比較結果一致。
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
