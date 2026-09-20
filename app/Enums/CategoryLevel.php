<?php

namespace App\Enums;

/**
 * 官方分類樹的層級。
 *
 * 值不是我們自己編的順序，而是商品回傳裡分類物件所在的陣列：
 * topCategories 是性別與全商品那一層，levelOne 是「下身類」這種大類，
 * levelTwo 是「寬褲」這種品項，levelThree 是官方再細分的錨點。
 */
enum CategoryLevel: int
{
    case Top = 0;
    case One = 1;
    case Two = 2;
    case Three = 3;
}
