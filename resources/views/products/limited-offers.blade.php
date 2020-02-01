@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', "{$productPresenter->countProducts($limitedOffers)} 件商品期間限定特價中")

@section('metadata')
{{--  <meta property="og:image" content="{{ $productPresenter->getProductMainImageUrl($productInfo) }}" />  --}}
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="negative certificate icon"></i>
        <span class="header">{{ $productPresenter->countProducts($limitedOffers) }} 件商品期間限定特價中</span>
    </div>
    @include('products.cards', ['products' => $limitedOffers])
@endsection
