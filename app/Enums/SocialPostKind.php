<?php

namespace App\Enums;

/**
 * 社群熱推的貼文種類，值是資料庫裡存的字串。
 */
enum SocialPostKind: string
{
    case Post = 'post';
    // YouTube 一般影片、Facebook 影片與 Reels、X 帶影片或 GIF 的推文
    case Video = 'video';
    // Instagram Reels
    case Reel = 'reel';
    // YouTube Shorts，縮圖是直式的
    case Short = 'short';

    /** 卡片上要加播放標記的種類 */
    public function isVideo(): bool
    {
        return $this !== self::Post;
    }
}
