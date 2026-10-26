<?php

namespace App\Models;

use App\Enums\Brand;
use App\Support\Url;
use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    public const REGION_TW = 'tw';

    public const REGION_JP = 'jp';

    protected $fillable = [
        'barcode',
        'region',
        'brand',
        'product_code',
        'codes',
    ];

    protected $casts = [
        'brand' => Brand::class,
        'codes' => 'array',
    ];

    public function getOfficialUrlAttribute(): string
    {
        return $this->region === self::REGION_JP
            ? Url::officialJapanProduct($this->brand, $this->product_code)
            : Url::officialTaiwanProduct($this->brand, $this->product_code);
    }
}
