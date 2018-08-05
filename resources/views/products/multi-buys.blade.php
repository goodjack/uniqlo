@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', "{$productPresenter->countProducts($multiBuys)} 件商品合購優惠中")

@section('metadata')
{{--  <meta property="og:image" content="{{ $productPresenter->getProductMainImageUrl($productInfo) }}" />  --}}
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="clone faded icon"></i>
        <span class="header">{{ $productPresenter->countProducts($multiBuys) }} 件商品合購優惠中</span>
    </div>
    @include('products.cards', ['products' => $multiBuys])
@endsection
