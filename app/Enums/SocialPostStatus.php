<?php

namespace App\Enums;

/**
 * 社群熱推貼文能不能顯示在商品頁。連不上或平台暫時擋下時不改狀態，原本顯示的照樣顯示。
 */
enum SocialPostStatus: string
{
    case Ok = 'ok';
    // 刪文、轉私人、網址不支援
    case Failed = 'failed';
}
