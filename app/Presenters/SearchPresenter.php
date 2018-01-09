<?php

namespace App\Presenters;

use App\Presenters\ProductPresenter;

class SearchPresenter
{
    public function __construct(ProductPresenter $productPresenter)
    {
        $this->productPresenter = $productPresenter;
    }

    public function getSearchResultCards($searchResults)
    {
        $html = '<div class="ts doubling link cards four">';

        foreach ($searchResults->records as $result) {
            $html .= $this->getSearchResultCard($result);
        }
        
        $html .= '</div>';

        return $html;
    }

    public function getSearchResultCard($searchResult)
    {
        $productUrl = $this->getProductUrl($searchResult->id);

        $color = $searchResult->representativeSKU->color;
        $id = $searchResult->id;
        $name = $searchResult->name;

        $productImage = $this->productPresenter->getProductMainImageUrl($searchResult);

        $html = "<a class=\"ts negative card\" href=\"{$productUrl}\">";
        $html .= '<div class="image">';
        $html .= "<img src=\"{$productImage}\">";
        $html .= '</div>';
        $html .= '<div class="content">';
        $html .= "<div class=\"header\">{$name}</div>";
        $html .= "<div class=\"meta\">{$id}</div>";
        $html .= '</div>';
        $html .= '</a>';

        return $html;
    }

    public function getProductUrl($productID)
    {
        return action('ProductController@show', ['id' => $productID]);
    }
}
