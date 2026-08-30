@extends('layouts.master')

@php
    $title = $isProductCodeSearch ? "商品編號 {$query} 的搜尋結果" : "「{$query}」的搜尋結果";
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
        <span class="header">{{ $title }}</span>
        @unless ($isProductCodeSearch)
            <span class="description">
                共 {{ $hmallProducts->total() }} 件
                @if (!empty($ignoredKeywords))
                    <div class="ts hidden divider"></div>
                    <div class="ts horizontal basic circular label">
                        關鍵字最多 {{ count($keywords) }} 個，這次沒有用到「{{ implode('」「', $ignoredKeywords) }}」
                    </div>
                @endif
            </span>
        @endunless
    </div>

    @if ($isProductCodeSearch)
        @include('search.cards', ['hmallProducts' => $hmallProducts, 'products' => $products])
    @else
        @include('search.keyword-cards', ['hmallProducts' => $hmallProducts, 'query' => $query])
    @endif
@endsection
