<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JapanProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'l1Id',
        'brand',
        'product_id',
        'name',
        'gender_category',
        'rating_average',
        'rating_count',
        'main_images',
        'sub_images',
        'sub_videos',
    ];

    protected $casts = [
        'prices' => 'array',
    ];

    public function hmallProduct()
    {
        return $this->belongsTo(HmallProduct::class, 'l1Id', 'code');
    }

    public function mainImages(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $this->transformMedias($value),
            get: fn ($value) => $this->getMediaUrls($value)
        );
    }

    public function subImages(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $this->transformMedias($value),
            get: fn ($value) => $this->getMediaUrls($value)
        );
    }

    public function subVideos(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $this->transformMedias($value, 'video'),
            get: fn ($value) => $this->getMediaUrls($value)
        );
    }

    public function isStockout(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => isset($attributes['stockout_at']),
        );
    }

    public function hasVideos(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => count(json_decode($attributes['sub_videos'], true)) > 0,
        );
    }

    private function transformMedias(array $medias, string $type = 'image'): string
    {
        $result = [];
        foreach ($medias as $media) {
            if (isset($media[$type])) {
                $mediaUrl = $this->shortenUrl($media[$type]);
                $result[] = $mediaUrl;
            }
        }

        return json_encode($result);
    }

    private function getMediaUrls(string $shortenedUrls): array
    {
        $shortenedUrls = json_decode($shortenedUrls, true);

        $result = [];

        foreach ($shortenedUrls as $shortenedUrl) {
            $result[] = $this->getOriginUrl($shortenedUrl);
        }

        return $result;
    }

    private function shortenUrl(string $url): string
    {
        preg_match(
            '/^https:\/\/image\.uniqlo\.com\/(UQ|GU)\/ST3\/(AsianCommon|jp)\/imagesgoods\/([0-9]+)\/(item|sub|subvideo)\/([a-z0-9_.]+)$/',
            $url,
            $matches
        );

        if (count($matches) === 6) {
            $prefix = match ($matches[1]) {
                'UQ' => 'Uq',
                'GU' => 'Gu',
            };
            $prefix .= match ($matches[2]) {
                'AsianCommon' => 'Ac',
                'jp' => 'Jp',
            };
            $prefix .= $matches[3];
            $prefix .= match ($matches[4]) {
                'item' => 'I',
                'sub' => 'S',
                'subvideo' => 'V',
            };

            $url = "{$prefix}/{$matches[5]}";
        }

        return $url;
    }

    private function getOriginUrl(string $url): string
    {
        preg_match('/^(Uq|Gu)(Ac|Jp)([0-9]+)(I|S|V)\/([a-z0-9_.]+)$/', $url, $matches);

        if (count($matches) < 6) {
            return $url;
        }

        $brand = match ($matches[1]) {
            'Uq' => 'UQ',
            'Gu' => 'GU',
        };
        $country = match ($matches[2]) {
            'Ac' => 'AsianCommon',
            'Jp' => 'jp',
        };
        $id = $matches[3];
        $type = match ($matches[4]) {
            'I' => 'item',
            'S' => 'sub',
            'V' => 'subvideo',
        };
        $fileName = $matches[5];

        return "https://image.uniqlo.com/{$brand}/ST3/{$country}/imagesgoods/{$id}/{$type}/{$fileName}";
    }
}
