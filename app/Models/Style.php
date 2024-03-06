<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Style extends Model
{
    use HasFactory;

    protected $fillable = ['id'];

    public $incrementing = false;

    protected $keyType = 'string';

    public function products()
    {
        return $this->belongsToMany('App\Models\Product');
    }

    public function hmallProducts()
    {
        return $this->belongsToMany(HmallProduct::class);
    }

    public function getLargeImageUrlAttribute(): string
    {
        if ($this->type === 'old') {
            return "https://im.uniqlo.com/style/{$this->image_path}-xxxl.jpg";
        }

        return $this->getImageOriginalUrl() . '_r-1000-1333';
    }

    public function getImageUrlAttribute($value): string
    {
        if (! empty($value)) {
            return $value;
        }

        return $this->getImageOriginalUrl() . '_r-600-800';
    }

    public function getDetailUrlAttribute($value): string
    {
        if (! empty($value)) {
            return $value;
        }

        return $this->getWebsiteUrlPrefix() . $this->id;
    }

    private function getImageOriginalUrl(): string
    {
        return "https://api.fastretailing.com/ugc/v1/{$this->type}/gl/OFFICIAL_IMAGES/{$this->image_path}";
    }

    private function getWebsiteUrlPrefix(): string
    {
        if ($this->type === 'gu') {
            return 'https://www.gu-global.com/tw/zh_TW/stylingbook/officialstyling/';
        }

        return 'https://www.uniqlo.com/tw/zh_TW/stylingbook/officialstyling/';
    }
}
