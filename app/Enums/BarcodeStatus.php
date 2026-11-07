<?php

namespace App\Enums;

/**
 * 條碼查詢的結果，值就是回給前端的 status。
 */
enum BarcodeStatus: string
{
    // 網站有這件，直接跳商品頁
    case Found = 'found';
    // 官網查得到，但網站還沒收錄
    case NotListed = 'not_listed';
    // 不是 UNIQLO 或 GU 的條碼：不是店內碼，或官網都查不到
    case NotProduct = 'not_product';
    // 長度或檢查碼不對
    case Invalid = 'invalid';
    // 官網連不上、被擋，或用完查詢上限
    case Unavailable = 'unavailable';
}
