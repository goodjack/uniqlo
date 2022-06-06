<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StyleHint extends Model
{
    protected $fillable = ['country', 'outfit_id'];

    public function getStyleImageUrlAttribute($value)
    {
        if (Str::startsWith($value, 'http')) {
            return $value;
        }

        preg_match('/([a-z]+)([0-9]+)/', $value, $matches);

        $country = $matches[1];
        $number = $matches[2];
        $originalOutfitId = $this->getRawOriginal('original_source_url');

        return "https://api.fastretailing.com/ugc/v1/uq/{$country}/SR_IMAGES/ugc_stylehint_uq_{$country}_photo_{$number}_{$originalOutfitId}";
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

    public function setStyleImageUrlAttribute($value)
    {
        preg_match(
            '|https://api.fastretailing.com/ugc/v1/uq/([a-z]+)/SR_IMAGES/ugc_stylehint_uq_[a-z]+_photo_([0-9]+)_[0-9]+$|',
            $value,
            $matches
        );

        $this->attributes['style_image_url'] = "{$matches[1]}{$matches[2]}" ?? $value;
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
}
