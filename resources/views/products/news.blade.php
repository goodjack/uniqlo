@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', "{$productPresenter->countProducts($news)} 件新品")

@section('metadata')
{{--  <meta property="og:image" content="{{ $productPresenter->getProductMainImageUrl($productInfo) }}" />  --}}
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="certificate faded icon"></i>
        <span class="header">{{ $productPresenter->countProducts($news) }} 件新品</span>
    </div>
    @include('products.cards', ['products' => $news])
@endsection
