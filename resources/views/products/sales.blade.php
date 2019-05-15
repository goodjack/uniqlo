@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', "{$productPresenter->countProducts($sales)} 件商品特價中")

@section('metadata')
{{--  <meta property="og:image" content="{{ $productPresenter->getProductMainImageUrl($productInfo) }}" />  --}}
@endsection

@section('content')
    <div class="ts large fluid padded slate">
        <div class="image">
            <img src="https://images.pexels.com/photos/16170/pexels-photo.jpg?auto=compress&cs=tinysrgb&dpr=2&h=750&w=1260" style="opacity: 0.5;">
        </div>
        {{-- <i class="certificate faded icon"></i> --}}
        <span class="header">特價</span>
        <span class="description">{{ $productPresenter->countProducts($sales) }} 件商品特價中</span>
    </div>
    @include('products.cards', ['products' => $sales])
@endsection
