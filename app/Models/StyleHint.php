<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StyleHint extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand',
        'country',
        'outfit_id',
        'style_image_url',
        'original_source_url',
        'department_id',
        'model_height',
        'user_id',
        'user_name',
        'user_image',
        'user_type',
        'store_region',
        'store_name',
        'comment',
        'user_info',
        'hashtags',
        'gender',
        'published_at',
        'detected_items',
    ];

    protected $casts = [
        'user_info' => 'array',
        'hashtags' => 'array',
        'detected_items' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $styleHint) {
            $styleHint->source_outfit_id = $styleHint->resolveSourceOutfitId();
        });
    }

    /**
     * 原始穿搭編號相同就是同一則，沒有編號的各自算一則。
     */
    public static function outfitKeySql(string $table = 'style_hints'): string
    {
        return sprintf("COALESCE(`%1\$s`.`source_outfit_id`, CONCAT('#', `%1\$s`.`id`))", $table);
    }

    /**
     * 同一則穿搭各國版本共用的編號。不同來源的編號各成一套，加前綴避免撞號。
     */
    public function resolveSourceOutfitId(): ?string
    {
        $styleImageUrl = $this->attributes['style_image_url'] ?? null;

        if (! is_string($styleImageUrl) || ! preg_match('/^[a-z]+[0-9]+(?:gu)?(Igbiz|Olapic)?([0-9]*)$/', $styleImageUrl, $matches)) {
            return null;
        }

        [, $type, $number] = $matches;

        if ($type !== '') {
            $prefix = $type === 'Igbiz' ? 'instagram-business' : 'olapic';

            return $number === '' ? null : "{$prefix}:{$number}";
        }

        $originalOutfitId = (string) ($this->attributes['original_source_url'] ?? '');

        return ctype_digit($originalOutfitId) ? $originalOutfitId : null;
    }

    /**
     * 某件商品的穿搭，同一則只留一筆，新的在前：優先台灣，沒有才用日本、美國，同一國取最新的。
     * 一則穿搭標了這件商品兩個顏色也只出現一次。
     */
    public function scopeDistinctOutfitsForCode(Builder $query, string $code): Builder
    {
        $priority = fn (string $table) => "CASE `{$table}`.`country` WHEN 'tw' THEN 0 WHEN 'jp' THEN 1 WHEN 'us' THEN 2 ELSE 3 END";

        return $query
            ->select('style_hints.*')
            ->join('style_hint_items as items', 'items.style_hint_id', '=', 'style_hints.id')
            ->where('items.code', $code)
            ->whereNotExists(function ($better) use ($priority) {
                $better->selectRaw('1')
                    ->from('style_hints as better')
                    ->join('style_hint_items as better_items', function ($join) {
                        $join->on('better_items.style_hint_id', '=', 'better.id')
                            ->on('better_items.code', '=', 'items.code');
                    })
                    ->whereColumn('better.source_outfit_id', 'style_hints.source_outfit_id')
                    ->whereColumn('better.id', '<>', 'style_hints.id')
                    ->whereRaw(
                        "({$priority('better')} < {$priority('style_hints')} OR ({$priority('better')} = {$priority('style_hints')} AND `better`.`id` > `style_hints`.`id`))"
                    );
            })
            ->groupBy('items.style_hint_id')
            ->orderByDesc('items.style_hint_id');
    }

    /**
     * 某件商品有幾則不重複的穿搭，跟 distinctOutfitsForCode 列出的筆數相同。
     */
    public static function countDistinctOutfitsForCode(string $code): int
    {
        return (int) DB::table('style_hint_items as items')
            ->join('style_hints', 'style_hints.id', '=', 'items.style_hint_id')
            ->where('items.code', $code)
            ->selectRaw('count(distinct '.self::outfitKeySql().') as aggregate')
            ->value('aggregate');
    }

    /**
     * 官方辨識出的單品，每種「品類＋顏色」只留一筆：同一雙鞋常被辨識成左右兩隻、或同時標成鞋子與靴子。
     *
     * @param  iterable<int, object|array<string, mixed>>  $attributes
     * @return array<int, array{category: string, color: string}>
     */
    public static function detectedItemsFromUgc(iterable $attributes): array
    {
        $items = [];

        foreach ($attributes as $attribute) {
            $category = data_get($attribute, 'intermediate_category');
            $color = data_get($attribute, 'detected_color');

            if (! is_string($category) || $category === '' || ! is_string($color) || $color === '') {
                continue;
            }

            $items["{$category}|{$color}"] = ['category' => $category, 'color' => $color];
        }

        return array_values($items);
    }

    public function hmallProducts()
    {
        return $this->belongsToMany(
            HmallProduct::class,
            'style_hint_items',
            'style_hint_id',
            'code',
            'id',
            'code'
        );
    }

    public function getStyleImageUrlAttribute($value)
    {
        preg_match('/^([a-z]+)([0-9]+)(gu)?(Igbiz|Olapic)?([0-9]*)$/', $value, $matches);

        if (count($matches) < 3) {
            return $value;
        }

        $matches = collect($matches);

        $country = $matches->get(1);
        $number = $matches->get(2);
        $brand = $matches->get(3) ?: 'uq';
        [$type, $originalOutfitId] = $this->getTypeAndOriginalOutfitId($matches);

        return "https://api.fastretailing.com/ugc/v1/{$brand}/{$country}/SR_IMAGES/ugc_{$type}_{$brand}_{$country}_photo_{$number}_{$originalOutfitId}";
    }

    public function getUserImageAttribute($value)
    {
        switch ($value) {
            case 'Stylehint':
                return 'https://api.fastretailing.com/ugc/SR_NETWORK_IMAGES/stylehint.png';
            case 'Olapic':
                return 'https://api.fastretailing.com/ugc/SR_NETWORK_IMAGES/olapic.png';
            case 'CdnIgbiz':
                return 'https://static.shuttlerock-cdn.com/images/social-user-icons/instagram_business.png';
            case 'Igbiz':
                return 'https://api.fastretailing.com/ugc/SR_NETWORK_IMAGES/instagram_business.png';
        }

        preg_match('/^([a-z]+)([0-9]+)(gu)?$/', $value, $matches);

        if (count($matches) < 3) {
            return $value;
        }

        $matches = collect($matches);

        $country = $matches->get(1);
        $number = $matches->get(2);
        $brand = $matches->get(3) ?: 'uq';

        return "https://api.fastretailing.com/ugc/v1/{$brand}/{$country}/SR_IMAGES/ugc_stylehint_user_{$number}";
    }

    public function getOriginalSourceUrlAttribute($value)
    {
        if (Str::startsWith($value, 'http')) {
            return $value;
        }

        if ($this->country === 'us') {
            return "https://www.stylehint.com/us/en/outfit/{$value}";
        }

        return "https://www.stylehint.com/jp/ja/outfit/{$value}";
    }

    public function getOfficialSiteUrlAttribute()
    {
        $outfitId = $this->outfit_id;
        $country = $this->country;
        $brand = $this->brand;

        if ($country === 'tw' && $brand === 'UNIQLO') {
            return "https://www.uniqlo.com/tw/zh_TW/stylingbook/stylehint/{$outfitId}";
        }

        if ($country === 'tw' && $brand === 'GU') {
            return "https://www.gu-global.com/tw/zh_TW/stylingbook/stylehint/{$outfitId}";
        }

        if ($country === 'us') {
            return "https://www.uniqlo.com/us/en/stylehint/{$outfitId}";
        }

        return "https://www.uniqlo.com/jp/ja/stylehint/{$outfitId}";
    }

    public function getImageUrlAttribute()
    {
        return "{$this->style_image_url}_r-600-800";
    }

    public function getLargeImageUrlAttribute()
    {
        return "{$this->style_image_url}_r-1000-1333";
    }

    public function getUserNameAttribute($value)
    {
        return $value ?? data_get($this->user_info, 'name');
    }

    public function setStyleImageUrlAttribute($value)
    {
        $styleImageUrl = $value;

        preg_match(
            '/https:\/\/api.fastretailing.com\/ugc\/v1\/(uq|gu)\/([a-z]+)\/SR_IMAGES\/ugc_(stylehint|instagram-business|olapic)_(?:uq|gu)_[a-z]+_photo_([0-9]+)_([0-9]+)$/',
            $value,
            $matches
        );

        if (count($matches) === 6) {
            $suffix = $matches[1] === 'uq' ? '' : 'gu';
            $suffix .= $matches[3] === 'instagram-business' ? "Igbiz{$matches[5]}" : '';
            $suffix .= $matches[3] === 'olapic' ? "Olapic{$matches[5]}" : '';

            $styleImageUrl = "{$matches[2]}{$matches[4]}{$suffix}";
        }

        $this->attributes['style_image_url'] = $styleImageUrl;
    }

    public function setUserImageAttribute($value)
    {
        switch ($value) {
            case 'https://api.fastretailing.com/ugc/SR_NETWORK_IMAGES/stylehint.png':
            case 'https:\/\/api.fastretailing.com\/ugc\/SR_NETWORK_IMAGES\/stylehint.png':
                $this->attributes['user_image_url'] = 'Stylehint';

                return;
            case 'https://api.fastretailing.com/ugc/SR_NETWORK_IMAGES/olapic.png':
            case 'https:\/\/api.fastretailing.com\/ugc\/SR_NETWORK_IMAGES\/olapic.png':
                $this->attributes['user_image_url'] = 'Olapic';

                return;
            case '//static.shuttlerock-cdn.com/images/social-user-icons/instagram_business.png':
            case '\/\/static.shuttlerock-cdn.com\/images\/social-user-icons\/instagram_business.png':
                $this->attributes['user_image_url'] = 'CdnIgbiz';

                return;
            case 'https://api.fastretailing.com/ugc/SR_NETWORK_IMAGES/instagram_business.png':
            case 'https:\/\/api.fastretailing.com\/ugc\/SR_NETWORK_IMAGES\/instagram_business.png':
                $this->attributes['user_image_url'] = 'Igbiz';

                return;
        }

        $styleImageUrl = $value;

        preg_match(
            '/https:\/\/api.fastretailing.com\/ugc\/v1\/(uq|gu)\/([a-z]+)\/SR_IMAGES\/ugc_stylehint_user_([0-9]+)$/',
            $value,
            $matches
        );

        if (count($matches) === 4) {
            $suffix = $matches[1] === 'uq' ? '' : 'gu';

            $styleImageUrl = "{$matches[2]}{$matches[3]}{$suffix}";
        }

        $this->attributes['user_image_url'] = $styleImageUrl;
    }

    public function setOriginalSourceUrlAttribute($value)
    {
        preg_match(
            '|https://www.stylehint.com/[a-z]+/[a-z]+/outfit/([0-9]+)$|',
            $value,
            $matches
        );

        $this->attributes['original_source_url'] = $matches[1] ?? $value;
    }

    private function getTypeAndOriginalOutfitId(Collection $matches)
    {
        if ($matches->get(4) === 'Igbiz') {
            return ['instagram-business', $matches->get(5)];
        }

        if ($matches->get(4) === 'Olapic') {
            return ['olapic', $matches->get(5)];
        }

        return ['stylehint', $this->getRawOriginal('original_source_url')];
    }
}
