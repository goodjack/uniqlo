<?php

namespace App\Presenters;

class ProductPresenter
{
    public function getStyleDictionaryImages($styleDictionary)
    {
        $html = '';
        foreach ($styleDictionary->imgs as $img) {
            $html += getStyleDictionaryImageTag($styleDictionary->imgdir, $img->fnm);
        }

        return $html;
    }

    public function getStyleDictionaryImageTag($imgDir, $imgFnm)
    {
        return "<img src=\"http://www.uniqlo.com/{$imgDir}{$imgFnm}.jpg\">";
    }
}
