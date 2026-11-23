<?php

namespace App\Services;

use App\Models\HmallProduct;
use App\Models\StyleHint;
use App\Models\StyleHintItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 商品頁的穿搭統計：從已存的網友穿搭算出最多人穿的顏色、男女比例、身高分布、
 * 常一起穿的品類與搭配單品的顏色。每天排程算好存快取，頁面只讀。
 *
 * 同一則穿搭的多國版本只算一次（跟商品頁列出的穿搭同一套去重）。
 */
class OutfitStatsService
{
    /**
     * 則數太少時比例沒有參考價值。
     */
    public const MIN_OUTFITS = 10;

    /**
     * 只看最近這麼多則，穿搭很多的商品也能在固定時間內算完。
     */
    public const SAMPLE_LIMIT = 1000;

    private const CACHE_KEY = 'hmall_product:outfit_stats:%s';

    private const PAIRED_CATEGORY_LIMIT = 8;

    private const DETECTED_PAIR_LIMIT = 10;

    /**
     * 身高每幾公分一段。
     */
    public const HEIGHT_BAND = 5;

    private const MIN_HEIGHT = 50;

    private const MAX_HEIGHT = 220;

    /**
     * 依性別和身高看穿的尺寸時的身高切點。男生身高中位數比女生高十幾公分，切點各自落在中間；
     * 男生只切一刀，165 公分左右的男生那組才常有足夠的則數。
     */
    private const SIZE_HEIGHT_CUTS = ['women' => [160, 170], 'men' => [175]];

    /**
     * 商品名稱關鍵字對到官方辨識的品類，照順序比對：鞋、包、帽要比「針織」「上衣」先判斷。
     */
    private const DETECTED_CATEGORY_KEYWORDS = [
        'shoes' => ['鞋'],
        'bags' => ['包'],
        'hats' => ['帽'],
        'dress' => ['洋裝'],
        'outerwears' => ['外套', '大衣', '夾克', '羽絨'],
        'skirts' => ['裙'],
        'bottoms' => ['褲'],
        'tops' => ['T恤', '上衣', '襯衫', '針織', '毛衣', '背心', 'BRATOP', 'POLO', '衫'],
    ];

    /**
     * 官方穿搭資料的性別編號。
     */
    private const GENDERS = [1 => 'men', 2 => 'women', 3 => 'kids', 4 => 'baby'];

    /**
     * 商品代號對到的主分類名稱，同一輪查過就不再查。
     *
     * @var array<string, string|null>
     */
    private array $categoryNames = [];

    public function __construct(private CategoryService $categoryService) {}

    public function get(HmallProduct $product): ?array
    {
        return $product->code === null ? null : Cache::get($this->cacheKey($product->code));
    }

    /**
     * 重算並寫進快取；則數不夠時清掉舊的。沒有商品代號的商品對不到穿搭，不算也不寫快取。
     */
    public function refresh(HmallProduct $product): ?array
    {
        if ($product->code === null) {
            return null;
        }

        $stats = $this->compute($product);
        $key = $this->cacheKey($product->code);

        if ($stats === null) {
            Cache::forget($key);
        } else {
            Cache::forever($key, $stats);
        }

        return $stats;
    }

    public function compute(HmallProduct $product): ?array
    {
        if ($product->code === null) {
            return null;
        }

        $outfits = StyleHint::query()
            ->distinctOutfitsForCode($product->code)
            ->select(['style_hints.id', 'style_hints.gender', 'style_hints.model_height', 'style_hints.detected_items'])
            ->limit(self::SAMPLE_LIMIT)
            ->get();

        if ($outfits->count() < self::MIN_OUTFITS) {
            return null;
        }

        $itemsByOutfit = StyleHintItem::query()
            ->whereIn('style_hint_id', $outfits->pluck('id'))
            ->get(['style_hint_id', 'code', 'original_product_id', 'item_size'])
            ->groupBy('style_hint_id');

        // 色號來自商品編號（貨號-色號），編號不帶色號的不列入
        [$colors, $coloredOutfits] = $this->countOwnItems($product->code, $outfits, $itemsByOutfit, 'code',
            fn (StyleHintItem $item) => preg_match('/^\d+-(\w+)$/', (string) $item->original_product_id, $matches) ? $matches[1] : null);
        $sizeOf = fn (StyleHintItem $item) => self::normalizeSize($item->item_size);
        [$sizes, $sizedOutfits] = $this->countOwnItems($product->code, $outfits, $itemsByOutfit, 'size', $sizeOf);
        [$heights, $measuredOutfits] = $this->countHeights($outfits);
        [$detectedPairs, $detectedOutfits] = $this->countDetectedPairs($outfits, self::detectedCategoryOf((string) $product->name));

        return [
            'outfits' => $outfits->count(),
            'colors' => $colors,
            'colored_outfits' => $coloredOutfits,
            'sizes' => $sizes,
            'sized_outfits' => $sizedOutfits,
            'sizes_by_gender_height' => $this->sizesByGenderHeight($product->code, $outfits, $itemsByOutfit, $sizeOf),
            'genders' => $this->countGenders($outfits),
            'heights' => $heights,
            'measured_outfits' => $measuredOutfits,
            'paired_categories' => $this->countPairedCategories($product->code, $itemsByOutfit),
            'detected_outfits' => $detectedOutfits,
            'detected_pairs' => $detectedPairs,
        ];
    }

    /**
     * 穿搭裡這件商品的某個屬性（顏色、尺寸），每則穿搭每個值只算一次；沒有值的穿搭不列入分母。
     *
     * @param  callable(StyleHintItem): ?string  $valueOf
     * @return array{0: array<int, array<string, string|int>>, 1: int}
     */
    private function countOwnItems(string $code, Collection $outfits, Collection $itemsByOutfit, string $keyName, callable $valueOf): array
    {
        $group = $this->countOwnItemsBy($code, $outfits, $itemsByOutfit, $valueOf, fn () => 'all', $keyName)['all'] ?? null;

        return [$group['values'] ?? [], $group['outfits'] ?? 0];
    }

    /**
     * 同上，但先依穿搭的某個屬性（例如性別加身高）分組，各組各自算；分不出組的穿搭不列入。
     *
     * @param  callable(StyleHintItem): ?string  $valueOf
     * @param  callable(StyleHint): string|int|null  $groupOf
     * @return array<string|int, array{outfits: int, values: array<int, array<string, string|int>>}>
     */
    private function countOwnItemsBy(string $code, Collection $outfits, Collection $itemsByOutfit, callable $valueOf, callable $groupOf, string $keyName): array
    {
        $groups = [];

        foreach ($outfits as $outfit) {
            $group = $groupOf($outfit);
            if ($group === null) {
                continue;
            }

            $values = collect($itemsByOutfit->get($outfit->id, []))
                ->where('code', $code)
                ->map($valueOf)
                ->filter(fn (?string $value) => $value !== null && $value !== '')
                ->unique();

            if ($values->isEmpty()) {
                continue;
            }

            $groups[$group]['outfits'] = ($groups[$group]['outfits'] ?? 0) + 1;
            foreach ($values as $value) {
                $groups[$group]['counts'][$value] = ($groups[$group]['counts'][$value] ?? 0) + 1;
            }
        }

        return array_map(
            fn (array $group) => ['outfits' => $group['outfits'], 'values' => $this->rank($group['counts'], $keyName)],
            $groups
        );
    }

    /**
     * 男生、女生各自依身高分組穿的尺寸，同性別矮到高排。兒童、嬰幼兒不列入。
     *
     * @return array<int, array{gender: string, from: int|null, until: int|null, outfits: int, sizes: array<int, array{size: string, count: int}>}>
     */
    private function sizesByGenderHeight(string $code, Collection $outfits, Collection $itemsByOutfit, callable $sizeOf): array
    {
        $groups = $this->countOwnItemsBy($code, $outfits, $itemsByOutfit, $sizeOf, function (StyleHint $outfit) {
            $gender = self::GENDERS[(int) $outfit->gender] ?? null;
            $height = $this->validHeight($outfit->model_height);

            if (! isset(self::SIZE_HEIGHT_CUTS[$gender]) || $height === null) {
                return null;
            }

            $from = null;
            foreach (self::SIZE_HEIGHT_CUTS[$gender] as $cut) {
                if ($height < $cut) {
                    return "{$gender}|{$from}|{$cut}";
                }
                $from = $cut;
            }

            return "{$gender}|{$from}|";
        }, 'size');

        $rows = [];
        foreach ($groups as $key => $group) {
            [$gender, $from, $until] = explode('|', $key);
            $rows[] = [
                'gender' => $gender,
                'from' => $from === '' ? null : (int) $from,
                'until' => $until === '' ? null : (int) $until,
                'outfits' => $group['outfits'],
                'sizes' => $group['values'],
            ];
        }

        // 畫面上的順序看過濾後的則數，由 presenter 排；這裡只固定順序讓每天算出來一樣
        usort($rows, fn (array $a, array $b) => [$a['gender'], $a['from'] ?? 0] <=> [$b['gender'], $b['from'] ?? 0]);

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    private function countGenders(Collection $outfits): array
    {
        $counts = $outfits
            ->map(fn (StyleHint $outfit) => self::GENDERS[(int) $outfit->gender] ?? null)
            ->filter()
            ->countBy()
            ->all();

        return collect($this->rank($counts, 'gender'))->pluck('count', 'gender')->all();
    }

    /**
     * @return array{0: array<int, array{from: int, count: int}>, 1: int}
     */
    private function countHeights(Collection $outfits): array
    {
        $bands = $outfits
            ->map(fn (StyleHint $outfit) => $this->heightBand($outfit->model_height))
            ->filter(fn (?int $band) => $band !== null);

        $heights = $bands->countBy()
            ->sortKeys()
            ->map(fn (int $count, int $from) => ['from' => $from, 'count' => $count])
            ->values()
            ->all();

        return [$heights, $bands->count()];
    }

    private function heightBand(mixed $height): ?int
    {
        $height = $this->validHeight($height);

        return $height === null ? null : intdiv($height, self::HEIGHT_BAND) * self::HEIGHT_BAND;
    }

    private function validHeight(mixed $height): ?int
    {
        if (! is_numeric($height)) {
            return null;
        }

        $height = (int) $height;

        return $height < self::MIN_HEIGHT || $height > self::MAX_HEIGHT ? null : $height;
    }

    /**
     * 同一則穿搭標了同品類的兩件商品只算一次。
     *
     * @return array<int, array{name: string, count: int}>
     */
    private function countPairedCategories(string $code, Collection $itemsByOutfit): array
    {
        $otherCodesByOutfit = $itemsByOutfit->map(
            fn (Collection $items) => $items->pluck('code')->filter()->reject(fn (string $other) => $other === $code)->unique()
        );

        $this->loadCategoryNames($otherCodesByOutfit->flatten()->unique());

        $counts = [];
        foreach ($otherCodesByOutfit as $otherCodes) {
            $names = $otherCodes->map(fn (string $other) => $this->categoryNames[$other] ?? null)->filter()->unique();

            foreach ($names as $name) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        return array_slice($this->rank($counts, 'name'), 0, self::PAIRED_CATEGORY_LIMIT);
    }

    /**
     * @param  Collection<int, string>  $codes
     */
    private function loadCategoryNames(Collection $codes): void
    {
        $missing = $codes->reject(fn (string $code) => array_key_exists($code, $this->categoryNames))->values();

        if ($missing->isEmpty()) {
            return;
        }

        // 同代號有多筆商品時固定用最早建立的那筆，每天挑到的分類才一樣
        $products = HmallProduct::query()
            ->with('categories')
            ->whereIn('code', $missing)
            ->orderBy('id')
            ->get(['id', 'code', 'brand', 'gender'])
            ->groupBy('code')
            ->map(fn (Collection $sameCode) => $sameCode->first());

        foreach ($missing as $code) {
            $product = $products->get($code);
            $this->categoryNames[$code] = $product === null
                ? null
                : $this->categoryService->getPrimaryCategory($product)?->name;
        }
    }

    /**
     * 只算有辨識資料的穿搭；辨識結果存檔時已經是每種「品類＋顏色」一筆。
     * 跟這件商品同品類的不算：那多半就是這件商品本身（牛仔褲的穿搭幾乎都辨識出藍色褲子）。
     *
     * @return array{0: array<int, array{category: string, color: string, count: int}>, 1: int}
     */
    private function countDetectedPairs(Collection $outfits, ?string $ownCategory): array
    {
        $detected = $outfits->filter(fn (StyleHint $outfit) => is_array($outfit->detected_items));
        $counts = [];

        foreach ($detected as $outfit) {
            $pairs = collect($outfit->detected_items)
                ->reject(fn (array $item) => $item['category'] === $ownCategory)
                ->map(fn (array $item) => "{$item['category']}|{$item['color']}")
                ->unique();

            foreach ($pairs as $pair) {
                $counts[$pair] = ($counts[$pair] ?? 0) + 1;
            }
        }

        $ranked = array_map(function (array $row) {
            [$category, $color] = explode('|', $row['pair'], 2);

            return ['category' => $category, 'color' => $color, 'count' => $row['count']];
        }, $this->rank($counts, 'pair'));

        return [array_slice($ranked, 0, self::DETECTED_PAIR_LIMIT), $detected->count()];
    }

    /**
     * 同一個尺寸在不同穿搭的寫法不一（「25inch」與「25」、「ONE SIZE」與「One size」），統一成同一種。
     */
    private static function normalizeSize(?string $size): ?string
    {
        if ($size === null) {
            return null;
        }

        $size = preg_replace('/\s*INCH$/', '', strtoupper(trim($size)));

        return $size === '' ? null : $size;
    }

    /**
     * 從商品名稱判斷它在官方辨識結果裡屬於哪一類，判斷不出來回 null（不排除任何品類）。
     * 依序比對，先比對到的算數；「連帽」「裙擺」不是帽子與裙子，先拿掉再比。
     */
    public static function detectedCategoryOf(string $name): ?string
    {
        $name = str_replace(['連帽', '裙擺'], '', $name);

        foreach (self::DETECTED_CATEGORY_KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * 多的在前，一樣多照名稱排，每天算出來的順序才固定。
     *
     * @param  array<string|int, int>  $counts
     * @return array<int, array<string, string|int>>
     */
    private function rank(array $counts, string $keyName): array
    {
        $rows = [];
        foreach ($counts as $key => $count) {
            $rows[] = [$keyName => (string) $key, 'count' => $count];
        }

        usort($rows, fn (array $a, array $b) => [$b['count'], $a[$keyName]] <=> [$a['count'], $b[$keyName]]);

        return $rows;
    }

    private function cacheKey(string $code): string
    {
        return sprintf(self::CACHE_KEY, $code);
    }
}
