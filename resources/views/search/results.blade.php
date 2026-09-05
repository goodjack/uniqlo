@extends('layouts.master')

@php
    $title = $isProductCodeSearch ? "商品編號 {$query} 的搜尋結果" : "「{$query}」的搜尋結果";

    $subtitle = $isProductCodeSearch
        ? "商品編號 {$query}"
        : "「{$query}」共 {$hmallProducts->total()} 件";
@endphp

@section('title', $title)

@section('metadata')
    @unless ($isProductCodeSearch)
        {{-- 搜尋結果頁不需要被索引，內容是既有商品頁的重組 --}}
        <meta name="robots" content="noindex, follow" />
    @endunless
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="search faded icon"></i>
        <span class="header">搜尋結果</span>
        <span class="description">{{ $subtitle }}</span>
    </div>

    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container">
            @if (!empty($ignoredKeywords))
                <div class="ts small info message">
                    關鍵字最多 {{ count($keywords) }} 個，這次沒有用到「{{ implode('」「', $ignoredKeywords) }}」
                </div>
            @endif

            @if ($isProductCodeSearch)
                @include('search.cards', ['hmallProducts' => $hmallProducts, 'products' => $products])
            @else
                @include('search.keyword-cards', ['hmallProducts' => $hmallProducts, 'query' => $query])
            @endif
        </div>
    </div>
@endsection
