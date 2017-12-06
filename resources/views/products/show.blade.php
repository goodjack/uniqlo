@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', '查詢')

@section('content')
        <!-- 片段 -->
        <div class="ts segment">
            <div class="ts grid">
                <div class="five wide column">
                    {!! $productPresenter->getProductMainImage($productInfo) !!}
                </div>
                <div class="eleven wide column">
                    {!! $productPresenter->getProductName($productInfo) !!}
                    {!! $productPresenter->getProductTag($productInfo) !!}
                    {!! $productPresenter->getProductComment($productInfo) !!}
                </div>
            </div>

            <div class="ts separated right floated buttons">
                {!! $productPresenter->getSalePrice($productInfo) !!}
                {!! $productPresenter->getUniqloLinkButton($productInfo) !!}
            </div>
        
        </div>
        <!-- / 片段 -->
        <div class="ts grid">
            {!! $productPresenter->getSubImages($productInfo) !!}
            {!! $productPresenter->getStyleDictionaryImages($styleDictionary) !!}
        </div>
@endsection