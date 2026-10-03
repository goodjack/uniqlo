<?php

namespace App\Enums;

/**
 * 官方分類樹的層級，對應商品回傳裡分類物件所在的陣列：topCategories（性別與
 * 全商品）、levelOne（「下身類」這種大類）、levelTwo（「寬褲」這種品項）、
 * levelThree（官方再細分的錨點）。
 */
enum CategoryLevel: int
{
    case Top = 0;
    case One = 1;
    case Two = 2;
    case Three = 3;
}
