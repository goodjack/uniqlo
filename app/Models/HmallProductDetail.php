<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, array{medium: string, large: string}>|null $main_images
 * @property array<int, string>|null $videos
 * @property array<int, int>|null $rating_levels 五星到一星
 * @property array<int, array{image: string|null, text: string}>|null $features
 * @property array<int, array{title: string, content: string}>|null $material_care
 * @property array<string, array{etag?: string, last_modified?: string}>|null $validators
 */
class HmallProductDetail extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'main_images' => 'array',
        'videos' => 'array',
        'rating_levels' => 'array',
        'features' => 'array',
        'material_care' => 'array',
        'validators' => 'array',
        'fetched_at' => 'datetime',
        'next_fetch_at' => 'datetime',
        'stale_since' => 'datetime',
        'content_changed_at' => 'datetime',
        'last_failed_at' => 'datetime',
    ];

    public function hmallProduct(): BelongsTo
    {
        return $this->belongsTo(HmallProduct::class);
    }
}
