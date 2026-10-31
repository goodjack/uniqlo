<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'notion_created_at' => 'datetime',
        'notion_edited_at' => 'datetime',
        'fetched_at' => 'datetime',
        'cover_expires_at' => 'datetime',
        'sort' => 'integer',
    ];

    public function codes()
    {
        return $this->hasMany(SocialPostCode::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'ok');
    }

    public function scopeDisplayOrder(Builder $query): Builder
    {
        return $query->orderByRaw('sort IS NULL')
            ->orderBy('sort')
            ->orderByDesc('notion_created_at');
    }

    /**
     * 卡片上的圖：YouTube 用官方縮圖；IG、Threads、X 的 og:image 經自己的網址轉送給 CDN 快取，
     * v 跟著貼文網址變，換了貼文才會拿到新圖。
     */
    public function getImageUrlAttribute(): ?string
    {
        if ($this->youtube_id) {
            return "https://i.ytimg.com/vi/{$this->youtube_id}/".($this->kind === 'Shorts' ? 'oardefault' : 'hqdefault').'.jpg';
        }

        return $this->cover_url ? route('social-posts.image', ['socialPost' => $this->id, 'v' => crc32((string) $this->canonical_url)]) : null;
    }

    /** IG、Threads 的作者是「名稱 (@帳號)」，卡片上只放帳號 */
    public function getHandleAttribute(): ?string
    {
        return preg_match('/@[\w.]+/', (string) $this->author, $m) ? $m[0] : $this->author;
    }
}
