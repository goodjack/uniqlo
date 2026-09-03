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
    {{-- 從 SEO 進來的人需要知道自己在整棵分類樹的哪裡，也給分類頁彼此建立內部連結 --}}
    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container">
            <div class="ts breadcrumb">
                <a class="section" href="{{ route('categories.index') }}">商品分類</a>
                @foreach ($breadcrumb as $crumb)
                    <i class="right chevron icon divider"></i>
                    @if ($loop->last)
                        <div class="active section">{{ $crumb->name }}</div>
                    @elseif ($crumb->level === \App\Enums\CategoryLevel::Top)
                        {{-- 頂層沒有自己的頁面，只當文字 --}}
                        <div class="section">{{ $brand->value }} {{ $crumb->name }}</div>
                    @else
                        <a class="section"
                            href="{{ route('categories.show', ['brand' => $brand->slug(), 'code' => $crumb->code]) }}">
                            {{ $crumb->name }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

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
            <x-toolbar>
                <x-slot:end>
                    @include('partials.tag-filter-button')
                </x-slot:end>

                @include('partials.tag-filter')
            </x-toolbar>

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

            {{-- 篩到 0 件時整個卡片容器與分頁都不該出現，只留一句話說明現在的狀況 --}}
            @if ($hmallProducts->isEmpty())
                @include('partials.empty-state', [
                    'icon' => 'search faded',
                    'title' => '沒有符合的商品',
                    'hint' => '試試看少選幾個條件',
                ])
            @else
                {{-- 分類本來就是單一性別的軸（男裝上衣），不像清單頁需要拆成四段 --}}
                <div class="ts doubling link cards four">
                    @each('hmall-products.card', $hmallProducts, 'hmallProduct')
                </div>

                @include('partials.pagination', ['paginator' => $hmallProducts])
            @endif
        </div>
    </div>
@endsection
