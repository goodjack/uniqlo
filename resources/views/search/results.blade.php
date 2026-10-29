@extends('layouts.master')

@php
    $title = $isProductCodeSearch ? "商品編號 {$query} 的搜尋結果" : "「{$query}」的搜尋結果";

    $subtitle = $isProductCodeSearch
        ? "商品編號 {$query}"
        : "「{$query}」共 {$hmallProducts->total()} 件";

    // 給 analytics.js 送 search_results，找出零結果的搜尋字。翻頁不重送，
    // 搜尋次數才不會被翻頁灌高；編號搜尋沒有分頁，件數含舊系統商品。搜尋框送出的編號
    // 只命中一件時，SearchController::index() 直接轉到商品頁、不經過這裡，這類搜尋不會送
    $analyticsResultCount = $isProductCodeSearch
        ? $hmallProducts->count() + $products->count()
        : $hmallProducts->total();
    $isFirstResultPage = $isProductCodeSearch || $hmallProducts->onFirstPage();
@endphp

@section('title', $title)

@section('metadata')
    @unless ($isProductCodeSearch)
        <meta name="robots" content="noindex, follow" />
    @endunless
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="search faded icon"></i>
        <h1 class="unstyled header">搜尋結果</h1>
        <span class="description">{{ $subtitle }}</span>
    </div>

    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container"
            @if ($isFirstResultPage)
                data-analytics-search-term="{{ $query }}" data-analytics-result-count="{{ $analyticsResultCount }}"
            @endif>
            @if (!empty($ignoredKeywords))
                <div class="ts small info message">
                    關鍵字最多 {{ count($keywords) }} 個，這次沒有用到「{{ implode('」「', $ignoredKeywords) }}」
                </div>
            @endif

            @if ($isProductCodeSearch)
                @include('search.cards', ['hmallProducts' => $hmallProducts, 'products' => $products, 'query' => $query])
            @else
                @include('search.keyword-cards', ['hmallProducts' => $hmallProducts, 'query' => $query])
            @endif
        </div>
    </div>
@endsection
