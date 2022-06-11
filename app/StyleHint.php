<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StyleHint extends Model
{
    protected $fillable = ['country', 'outfit_id'];

    protected $casts = [
        'user_info' => 'array',
        'hashtags' => 'array',
    ];

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

    public function getImageUrlAttribute()
    {
        return "{$this->style_image_url}_r-600-800";
    }

    public function getLargeImageUrlAttribute()
    {
        return "{$this->style_image_url}_r-1000-1333";
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
