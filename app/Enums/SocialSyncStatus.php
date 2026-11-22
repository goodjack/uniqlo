<?php

namespace App\Enums;

/**
 * 寫回 Notion「同步狀態」的選項，整理清單時看得出每一列為什麼有或沒有出現在網站上。值就是 Notion 上的選項名稱。
 */
enum SocialSyncStatus: string
{
    case Ok = '正常';
    // 顯示中，點開是站內的卡片，不是平台的嵌入
    case NotEmbeddable = '無法嵌入';
    case LoginRequired = '沒登入看不到';
    case Deleted = '已刪除';
    // 同一篇貼文在清單裡出現不只一次，網站只放排在最前面的那列
    case Duplicate = '重複';
    // 重複，但這列有排在前面那列沒有的貨號，刪掉這列那幾個商品頁就少了這篇
    case DuplicateWithExtraCodes = '重複，貨號不同';
    // 沒有網址、不支援的網址，或其他抓不到的原因
    case Failed = '抓取失敗';
}
