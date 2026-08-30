@extends('layouts.master')

@php
    $currentUrl = url()->current();
@endphp

@section('title', '商品分類')

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    <meta name="description" content="依商品分類瀏覽 UNIQLO 與 GU 的商品與價格 | UQ 搜尋" />
    <meta property="og:title" content="商品分類 | UQ 搜尋" />
    <meta property="og:url" content="{{ $currentUrl }}" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="sitemap faded icon"></i>
        <span class="header">商品分類</span>
        <span class="description">只列出目前還買得到商品的分類</span>
    </div>

    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container">
            @foreach ($groups as $group)
                <h2 class="ts large header">
                    {{ $group['category']->name }}
                    <div class="inline sub header">{{ $group['brand']->value }}</div>
                </h2>
                <div class="ts hidden divider"></div>
                @foreach ($group['children'] as $child)
                    <a class="ts basic label"
                        href="{{ route('categories.show', ['brand' => $group['brand']->slug(), 'code' => $child->code]) }}">
                        {{ $child->name }}
                        <span class="detail">{{ $child->hmall_products_count }}</span>
                    </a>
                @endforeach
                @if (!$loop->last)
                    <div class="ts hidden section divider"></div>
                @endif
            @endforeach
            <div class="ts hidden divider"></div>
        </div>
    </div>
@endsection
