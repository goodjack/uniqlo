@inject('searchPresenter', 'App\Presenters\SearchPresenter')
@extends('layouts.master')

@section('title', $query . " 的搜尋結果")

@section('metadata')
{{--  <meta property="og:image" content="{{ $searchPresenter->getProductMainImageUrl($productInfo) }}" />  --}}
@endsection

@section('content')
    <div class="ts padded fluid slate">
        <i class="search symbol icon"></i>
        <span class="header">{{ $query }} 的搜尋結果</span>
    </div>

    <div class="ts hidden divider">我是分隔線</div>

    <div class="ts doubling link cards four">
        @foreach ($products as $product)
        <a class="ts negative card" href="{{ $searchPresenter->getProductUrl($product) }}">
            <div class="image">
                <img src="{{ $product->main_image_url }}">
            </div>
            <div class="content">
                <div class="header">{{ $product->name }}</div>
                <div class="meta">{{ $product->id }}</div>
            </div>
        </a>
        @endforeach
    </div>
@endsection