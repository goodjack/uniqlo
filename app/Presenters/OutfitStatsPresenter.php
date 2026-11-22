<?php

namespace App\Presenters;

use App\Models\HmallProduct;
use App\Services\OutfitStatsService;

/**
 * 把快取裡的穿搭統計整理成商品頁的幾張卡片，每列是名稱與占比。
 */
class OutfitStatsPresenter
{
    private const ROWS_PER_CARD = 5;

    /**
     * 尺寸表每列只列最多人穿的幾個尺寸。
     */
    private const SIZES_PER_ROW = 3;

    /**
     * 尺寸表至少要有兩列夠資料：只剩一列時跟「最多人穿的尺寸」卡沒有差別。
     */
    private const MIN_TABLE_ROWS = 2;

    /**
     * 辨識結果的「顏色＋單品」組合多，要比其他卡片更多則才穩定。抽樣 22 件商品：辨識資料有 30 則以上的，
     * 最常見組合都占三成以上；不到 30 則的比例跳動大。
     */
    private const MIN_DETECTED_OUTFITS = 30;

    private const MIN_DETECTED_TOP_PERCENT = 30;

    /**
     * 辨識結果的組合裡低於一成的不列，只是零星出現。
     */
    private const MIN_DETECTED_ROW_PERCENT = 10;

    private const GENDERS = ['women' => '女', 'men' => '男', 'kids' => '兒童', 'baby' => '嬰幼兒'];

    /**
     * 官方辨識結果的品類與顏色；沒列到的照原文顯示。
     */
    private const DETECTED_CATEGORIES = [
        'shoes' => '鞋子',
        'outerwears' => '外套',
        'tops' => '上衣',
        'bottoms' => '褲子',
        'skirts' => '裙子',
        'dress' => '洋裝',
        'bags' => '包包',
        'hats' => '帽子',
        'accessories' => '配件',
        'eyewear' => '眼鏡',
    ];

    private const DETECTED_COLORS = [
        'black' => '黑色',
        'white' => '白色',
        'gray' => '灰色',
        'brown' => '棕色',
        'beige' => '米色',
        'blue' => '藍色',
        'navy' => '深藍色',
        'green' => '綠色',
        'yellow' => '黃色',
        'orange' => '橘色',
        'red' => '紅色',
        'pink' => '粉紅色',
        'purple' => '紫色',
    ];

    /**
     * @return array<int, array{title: string, base: int|null, rows: array<int, array{label: string, percent: int}>}>
     */
    public function cards(HmallProduct $product, array $stats): array
    {
        $outfits = $stats['outfits'];
        $colorNames = $this->colorNames($product);

        $cards = [
            $this->card('最多人穿的顏色', $outfits, $stats['colored_outfits'], array_map(
                fn (array $color) => [$colorNames[$color['code']] ?? "色號 {$color['code']}", $color['count']],
                $stats['colors']
            )),
            $this->sizeCard($outfits, $stats['sizes'] ?? [], $stats['sized_outfits'] ?? 0),
            $this->card('男女比例', $outfits, array_sum($stats['genders']), array_map(
                fn (string $gender, int $count) => [self::GENDERS[$gender] ?? $gender, $count],
                array_keys($stats['genders']),
                $stats['genders']
            )),
            $this->card('身高分布', $outfits, $stats['measured_outfits'], $this->heightRows($stats['heights'])),
            $this->card('最常一起穿的品類', $outfits, $outfits, array_map(
                fn (array $category) => [$category['name'], $category['count']],
                $stats['paired_categories']
            )),
            $this->detectedCard($outfits, $stats['detected_outfits'], $stats['detected_pairs']),
        ];

        return array_values(array_filter($cards));
    }

    /**
     * 男女各身高穿的尺寸。每列各自以那一列的則數算占比，不到 10 則的列不顯示，夠的列少於兩列整張不顯示；
     * 只有一種尺寸（例如包包的 ONE SIZE）時每列都是 100%，跟尺寸卡一樣不顯示。
     * 畫面上則數多的性別排前面，同性別矮到高。
     *
     * @return array{rows: array<int, array{label: string, outfits: int, sizes: array<int, array{size: string, percent: int}>}>}|null
     */
    public function sizeTable(array $stats): ?array
    {
        $rows = array_values(array_filter(
            $stats['sizes_by_gender_height'] ?? [],
            fn (array $row) => $row['outfits'] >= OutfitStatsService::MIN_OUTFITS
        ));

        if (count($rows) < self::MIN_TABLE_ROWS) {
            return null;
        }

        if (collect($rows)->pluck('sizes')->flatten(1)->pluck('size')->unique()->count() < 2) {
            return null;
        }

        $shownByGender = collect($rows)->groupBy('gender')->map->sum('outfits');
        usort($rows, fn (array $a, array $b) => [$shownByGender[$b['gender']], $a['gender'], $a['from'] ?? 0]
            <=> [$shownByGender[$a['gender']], $b['gender'], $b['from'] ?? 0]);

        return [
            'rows' => array_map(fn (array $row) => [
                'label' => $this->genderHeightLabel($row['gender'], $row['from'], $row['until']),
                'outfits' => $row['outfits'],
                'sizes' => array_map(
                    fn (array $size) => ['size' => $size['size'], 'percent' => (int) round($size['count'] / $row['outfits'] * 100)],
                    array_slice($row['sizes'], 0, self::SIZES_PER_ROW)
                ),
            ], $rows),
        ];
    }

    private function genderHeightLabel(string $gender, ?int $from, ?int $until): string
    {
        $who = (self::GENDERS[$gender] ?? $gender).'生';

        return match (true) {
            $from === null => "{$who}未滿 {$until} 公分",
            $until === null => "{$who} {$from} 公分以上",
            default => sprintf('%s %d–%d 公分', $who, $from, $until - 1),
        };
    }

    /**
     * 卡片的分母跟全部則數不同時才標出來，讀者才知道占比是對多少則算的。
     *
     * @param  array<int, array{0: string, 1: int}>  $rows
     */
    private function card(string $title, int $outfits, int $base, array $rows): ?array
    {
        if ($base < OutfitStatsService::MIN_OUTFITS || $rows === []) {
            return null;
        }

        return [
            'title' => $title,
            'base' => $base === $outfits ? null : $base,
            'rows' => array_map(
                fn (array $row) => ['label' => $row[0], 'percent' => (int) round($row[1] / $base * 100)],
                array_slice($rows, 0, self::ROWS_PER_CARD)
            ),
        ];
    }

    /**
     * 只有一種尺寸（例如包包的 ONE SIZE）時沒有資訊量，不顯示。
     *
     * @param  array<int, array{size: string, count: int}>  $sizes
     */
    private function sizeCard(int $outfits, array $sizes, int $base): ?array
    {
        if (count($sizes) < 2) {
            return null;
        }

        return $this->card('最多人穿的尺寸', $outfits, $base, array_map(
            fn (array $size) => [$size['size'], $size['count']],
            $sizes
        ));
    }

    /**
     * 則數不夠、或最常見的組合也不到三成時整張不顯示。不退回只看品類：
     * 幾乎每則穿搭都辨識出鞋子，只列品類沒有資訊量。
     *
     * @param  array<int, array{category: string, color: string, count: int}>  $pairs
     */
    private function detectedCard(int $outfits, int $base, array $pairs): ?array
    {
        if ($base < self::MIN_DETECTED_OUTFITS || $pairs === []) {
            return null;
        }

        if ($pairs[0]['count'] * 100 < $base * self::MIN_DETECTED_TOP_PERCENT) {
            return null;
        }

        $rows = array_filter($pairs, fn (array $pair) => $base * self::MIN_DETECTED_ROW_PERCENT <= $pair['count'] * 100);

        return $this->card('搭配單品的顏色', $outfits, $base, array_map(
            fn (array $pair) => [$this->detectedLabel($pair['category'], $pair['color']), $pair['count']],
            array_values($rows)
        ));
    }

    /**
     * 取人數最多的幾段，再照身高排，讀起來才像分布。
     *
     * @param  array<int, array{from: int, count: int}>  $heights
     * @return array<int, array{0: string, 1: int}>
     */
    private function heightRows(array $heights): array
    {
        $top = collect($heights)->sortByDesc('count')->take(self::ROWS_PER_CARD)->sortBy('from');

        return $top->map(fn (array $band) => [sprintf('%d–%d 公分', $band['from'], $band['from'] + OutfitStatsService::HEIGHT_BAND - 1), $band['count']])
            ->values()
            ->all();
    }

    private function detectedLabel(string $category, string $color): string
    {
        $categoryName = self::DETECTED_CATEGORIES[$category] ?? $category;

        if (isset(self::DETECTED_COLORS[$color])) {
            return self::DETECTED_COLORS[$color].$categoryName;
        }

        return "{$color} {$categoryName}";
    }

    /**
     * 官網的色名（例如「03 GRAY」），以色號為鍵。
     *
     * @return array<string, string>
     */
    private function colorNames(HmallProduct $product): array
    {
        $names = [];

        foreach (json_decode((string) $product->style_text, true) ?: [] as $text) {
            $colorCode = strtok((string) $text, ' ');
            if ($colorCode !== false) {
                $names[$colorCode] = $text;
            }
        }

        return $names;
    }
}
