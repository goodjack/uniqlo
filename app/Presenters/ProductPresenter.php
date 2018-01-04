<?php

namespace App\Presenters;

class ProductPresenter
{
    public function getProductMainImage($productInfo)
    {
        $imageUrl = $this->getProductMainImageUrl($productInfo);

        $html = "<img class=\"ts fluid rounded link image\" src=\"{$imageUrl}\">";

        return $html;
    }

    public function getProductMainImageUrl($productInfo)
    {
        $color = $productInfo->representativeSKU->color;
        $id = $productInfo->id;

        return "http://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$color}_{$id}.jpg";
    }

    public function getProductName($productInfo)
    {
        return $productInfo->name;
    }

    public function getProductHeader($productInfo)
    {
        $productName = $this->getProductName($productInfo);
        
        $html = "<h2 class=\"ts header\">{$productName}</h2>";

        return $html;
    }

    public function getProductTag($productInfo)
    {
        $html = '';

        $msg = $productInfo->representativeSKU->limitSalesEndMsg;
        if ($msg) {
            $html .= "<div class=\"ts negative label\">{$msg}</div>";
        }

        $new = $productInfo->new;
        if ($new) {
            $html .= "<div class=\"ts inverted label\">新品</div>";
        }

        return $html;
    }

    public function getProductComment($productInfo)
    {
        $comment = $productInfo->catchCopy;
        $material = $productInfo->material;
        $care = $productInfo->care;

        $html = "<p>{$comment}</p><p>{$material}</p><p>{$care}</p>";


        return $html;
    }

    public function getSalePrice($productInfo)
    {
        return "<button class=\"ts mini link button\"><h4>NT\${$productInfo->representativeSKU->salePrice}</h4></button>";
    }

    public function getUniqloLinkButton($productInfo)
    {
        return "<a class=\"ts mini negative basic labeled icon button\" href=\"http://www.uniqlo.com/tw/store/goods/{$productInfo->id}\" target=\"_blank\"><i class=\"external link icon\"></i> 前往官網</a>";
    }

    public function getSubImages($productInfo)
    {
        $html = '';
        foreach ($productInfo->subImages as $subImg) {
            $html .= $this->getSubImageTag($productInfo->id, $subImg);
        }

        return $html;
    }

    public function getSubImageTag($id, $subImg)
    {
        $link = "http://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/sub/{$subImg}.jpg";
        $imgUrl = $link;

        return $this->getImageTag($link, $imgUrl);
    }

    public function getStyleDictionaryImages($styleDictionary)
    {
        $html = '';
        foreach ($styleDictionary->imgs as $img) {
            $html .= $this->getStyleDictionaryImageTag($styleDictionary->imgdir, $img->id, $img->fnm);
        }

        return $html;
    }

    public function getStyleDictionaryImageTag($imgDir, $imgID, $imgFnm)
    {
        $link = "http://www.uniqlo.com/tw/stylingbook/detail.html#/detail/{$imgID}";
        $imgUrl = "http://www.uniqlo.com/{$imgDir}{$imgFnm}-xl.jpg";

        return $this->getImageTag($link, $imgUrl);
    }

    public function getImageTag($link, $imgUrl)
    {
        return "<a class=\"ts card\" href=\"{$link}\" target=\"_blank\"><div class=\"image\"><img src=\"{$imgUrl}\"></div></a>";
    }
}
