<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Enums\SocialPostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    protected $fillable = [
        'notion_page_id', 'notion_created_at', 'notion_edited_at', 'sort', 'source_url', 'canonical_url', 'platform',
        'kind', 'author', 'text', 'youtube_id', 'cover_url', 'cover_expires_at', 'status', 'fetched_at',
    ];

    protected $casts = [
        'notion_created_at' => 'datetime',
        'notion_edited_at' => 'datetime',
        'fetched_at' => 'datetime',
        'cover_expires_at' => 'datetime',
        'sort' => 'integer',
        'platform' => SocialPlatform::class,
        'kind' => SocialPostKind::class,
        'status' => SocialPostStatus::class,
    ];

    public function codes()
    {
        return $this->hasMany(SocialPostCode::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', SocialPostStatus::Ok);
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
            return "https://i.ytimg.com/vi/{$this->youtube_id}/".($this->kind === SocialPostKind::Short ? 'oardefault' : 'hqdefault').'.jpg';
        }

        return $this->cover_url ? route('social-posts.image', ['socialPost' => $this->id, 'v' => crc32((string) $this->canonical_url)]) : null;
    }

    /** IG、Threads 的作者是「名稱 (@帳號)」，卡片上只放帳號 */
    public function getHandleAttribute(): ?string
    {
        return preg_match('/@[\w.]+/', (string) $this->author, $m) ? $m[0] : $this->author;
    }
}
