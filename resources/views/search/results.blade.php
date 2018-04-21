@extends('layouts.master')

@section('title', $query . " 的搜尋結果")

@section('metadata')
{{--  <meta property="og:image" content="{{ $searchPresenter->getProductMainImageUrl($productInfo) }}" />  --}}
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="search faded icon"></i>
        <span class="header">{{ $query }} 的搜尋結果</span>
    </div>
    @include('products.cards', ['products' => $products])
@endsection