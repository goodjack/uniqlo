@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@php
$title = "{$productPresenter->countProducts($products)} 件{$typeName}";
@endphp

@section('title', $title)

@section('og-title', $typeName)

@section('metadata')
<link rel="canonical" href="{{ route($routeName) }}" />
<meta name="description" content="{{ \App\Support\SiteTitle::appendWithTagline($title) }}" />
<meta property="og:url" content="{{ route($routeName) }}" />
<meta property="og:description" content="{{ \App\Support\SiteTitle::appendWithTagline($title) }}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:creator" content="@littlegoodjack" />
<meta name="twitter:description" content="{{ \App\Support\SiteTitle::appendWithTagline($title) }}" />
@endsection

@section('css')
<style>
    i.most-reviewed.icon {
        color: #885A89 !important;
    }
</style>
@endsection

@section('content')
<div class="ts fluid slate">
    <i class="{{ $typeStyle }} {{ $typeIcon }} icon"></i>
    <span class="header">{{ $productPresenter->countProducts($products) }} 件{{ $typeName }}</span>
</div>
@include('products.cards', ['products' => $products])
@endsection
