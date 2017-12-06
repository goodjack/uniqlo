<?php

namespace App\Presenters;

class ProductPresenter
{
    public function getProductMainImage($productInfo)
    {
        // $l1GoodsCd = $productInfo->GoodsInfo->goods->l1GoodsCd;
        
        // $key = $productInfo->GoodsInfo->goods->dispL2GoodsKey;
        // $colorCd = $productInfo->GoodsInfo->goods->l2GoodsList->{$key}->L2GoodsInfo->colorCd;

        $color = $productInfo->representativeSKU->color;
        $id = $productInfo->id;

        $html = "<img class=\"ts fluid rounded link image\" src=\"http://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/item/{$color}_{$id}.jpg\">";

        return $html;
    }

    public function getProductName($productInfo)
    {
        $name = $productInfo->name;

        $html = "<h2 class=\"ts header\">{$name}</h2>";

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
        return "<button class=\"ts link button\"><h4>NT\${$productInfo->representativeSKU->salePrice}</h4></button>";
    }

    public function getUniqloLinkButton($productInfo)
    {
        return "<a href=\"http://www.uniqlo.com/tw/store/goods/{$productInfo->id}\"  target=\"_blank\"><button class=\"ts negative basic icon button\"><i class=\"external link icon\"></i> 前往官網</button></a>";
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
        return "<div class=\"four wide column\"><div class=\"ts fluid link images\"><a href=\"http://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/sub/{$subImg}.jpg\"  target=\"_blank\"><img src=\"http://im.uniqlo.com/images/tw/uq/pc/goods/{$id}/sub/{$subImg}.jpg\"></a></div></div>";
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
        return "<div class=\"four wide column\"><div class=\"ts fluid link images\"><a href=\"http://www.uniqlo.com/tw/stylingbook/detail.html#/detail/{$imgID}\"  target=\"_blank\"><img src=\"http://www.uniqlo.com/{$imgDir}{$imgFnm}-xl.jpg\"></a></div></div>";
    }
}
