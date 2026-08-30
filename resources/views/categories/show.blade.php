@extends('layouts.master')

@php
    $currentUrl = url()->current();
    $title = "{$category->name}（{$hmallProducts->total()} 件）";
@endphp

@section('title', $category->name)

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    <meta name="description" content="{{ $category->name }} 的 UNIQLO 與 GU 商品比價 | UQ 搜尋" />
    <meta property="og:title" content="{{ $category->name }} | UQ 搜尋" />
    <meta property="og:url" content="{{ $currentUrl }}" />
    <meta property="og:description" content="{{ $title }} | UNIQLO 比價 | UQ 搜尋" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="tags faded icon"></i>
        <span class="header">{{ $title }}</span>
        {{-- 分類不提供品牌篩選：兩家的分類 code 各成一套，一個分類只會有一家的商品 --}}
        <span class="description">
            <div class="ts @if ($brand === \App\Enums\Brand::Gu) info @else negative @endif label">
                {{ $brand->value }}
            </div>
        </span>
    </div>

    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container">
            @if ($children->isNotEmpty())
                <div class="ts hidden divider"></div>
                @foreach ($children as $child)
                    <a class="ts basic label"
                        href="{{ route('categories.show', ['brand' => $brand->slug(), 'code' => $child->code]) }}">
                        {{ $child->name }}
                        <span class="detail">{{ $child->hmall_products_count }}</span>
                    </a>
                @endforeach
                <div class="ts hidden section divider"></div>
            @endif

            {{-- 分類本來就是單一性別的軸（男裝上衣），不像清單頁需要拆成四段 --}}
            <div class="ts doubling link cards four">
                @each('hmall-products.card', $hmallProducts, 'hmallProduct')
            </div>

            @include('partials.pagination', ['paginator' => $hmallProducts])
        </div>
    </div>
@endsection
