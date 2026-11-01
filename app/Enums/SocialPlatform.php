<?php

namespace App\Enums;

/**
 * 社群熱推支援的平台，值是資料庫裡存的字串，也是頁面上 data-platform 的值。
 */
enum SocialPlatform: string
{
    case Youtube = 'youtube';
    case Instagram = 'instagram';
    case Threads = 'threads';
    case X = 'x';
    case Facebook = 'facebook';

    /** 只認這些網域，子網域也算（m.facebook.com、www.threads.com） */
    public function hosts(): array
    {
        return match ($this) {
            self::Youtube => ['youtube.com', 'youtu.be'],
            self::Instagram => ['instagram.com'],
            self::Threads => ['threads.com', 'threads.net'],
            self::X => ['x.com', 'twitter.com'],
            self::Facebook => ['facebook.com', 'fb.watch'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Youtube => 'YouTube',
            self::Instagram => 'Instagram',
            self::Threads => 'Threads',
            self::X => 'X',
            self::Facebook => 'Facebook',
        };
    }

    /** Font Awesome brands 的圖示名稱 */
    public function icon(): string
    {
        return $this === self::X ? 'x-twitter' : $this->value;
    }
}
