@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', $productPresenter->getProductName($productInfo))

@section('metadata')
<meta property="og:image" content="{{ $productPresenter->getProductMainImageUrl($productInfo) }}" />
@endsection

@section('content')
<!-- 片段 -->
<div class="ts segment">
    <div class="ts grid">
        <div class="five wide column">
            {!! $productPresenter->getProductMainImage($productInfo) !!}
        </div>
        <div class="eleven wide column">
            {!! $productPresenter->getProductHeader($productInfo) !!}
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

<div class="ts doubling four waterfall cards">
    {!! $productPresenter->getSubImages($productInfo) !!}
    {!! $productPresenter->getStyleDictionaryImages($styleDictionary) !!}
</div>
@endsection