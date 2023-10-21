<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StyleHint extends Model
{
    use HasFactory;

    protected $fillable = ['country', 'outfit_id'];

    protected $casts = [
        'user_info' => 'array',
        'hashtags' => 'array',
    ];

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

        if ($this->country === 'tw') {
            return "https://www.uniqlo.com/tw/zh_TW/staff-styling-detail.html?ugcId={$outfitId}";
        }

        if ($this->country === 'us') {
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
