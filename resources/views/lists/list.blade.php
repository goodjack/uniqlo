@extends('layouts.master')

@php
    $currentUrl = url()->current();
    $title = "{$count} 件{$typeName}";

    // 品牌、排序與標籤是三個獨立的軸，切換其中一個要保留另外兩個
    $queryFor = fn(array $changes) => http_build_query(
        array_filter(array_merge(request()->only(['brand', 'sort', 'tags']), $changes))
    );

    $currentBrand = in_array(request('brand'), ['UNIQLO', 'GU'], true) ? request('brand') : null;
    $selectedTagValues = collect(\App\Enums\ProductTag::fromValues((array) request('tags', [])))
        ->map->value
        ->all();

    // 副標講的是實際排序，不是預設排序：使用者選了價格排序之後還寫預設就是錯的
    $sortText = request('sort') === 'price-asc' ? '依價格由低到高排序' : $sortSummary;
@endphp

@section('title', $title)

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    <meta name="description" content="{{ $title }} | UNIQLO 比價 | UQ 搜尋" />
    <meta property="og:title" content="{{ $typeName }} | UQ 搜尋" />
    <meta property="og:url" content="{{ $currentUrl }}" />
    <meta property="og:description" content="{{ $title }} | UNIQLO 比價 | UQ 搜尋" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:creator" content="@littlegoodjack" />
    <meta name="twitter:title" content="{{ $typeName }} | UQ 搜尋" />
    <meta name="twitter:description" content="{{ $title }} | UNIQLO 比價 | UQ 搜尋" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="{{ $typeStyle }} {{ $typeIcon }} icon"></i>
        <span class="header">{{ $typeName }}</span>
        <span class="description">{{ $count }} 件，{{ $sortText }}</span>
    </div>

    <div class="ts container uq-page">
        <x-toolbar>
            <x-slot:start>
                {{-- 三顆分開的 pill 不是連在一起的按鈕群：連著的那組會被讀成一顆分段控制項， --}}
                {{-- 但這裡每一顆都是各自的網址、按了是換頁 --}}
                <a class="uq-control uq-pill {{ $currentBrand === null ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['brand' => null]) }}">全部</a>
                <a class="uq-control uq-pill {{ $currentBrand === 'UNIQLO' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'UNIQLO']) }}">UNIQLO</a>
                <a class="uq-control uq-pill {{ $currentBrand === 'GU' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'GU']) }}">GU</a>
            </x-slot:start>

            <x-slot:end>
                {{-- 原生 <select>，不吃 JavaScript。不掛 Tocas 的 dropdown：那組規則自帶 --}}
                {{-- 高度、圓角與箭頭，蓋起來比從 .uq-control 長還費事 --}}
                <form method="GET" action="{{ $currentUrl }}" class="uq-sort-form">
                    @if ($currentBrand)
                        <input type="hidden" name="brand" value="{{ $currentBrand }}">
                    @endif
                    @foreach ($selectedTagValues as $tagValue)
                        <input type="hidden" name="tags[]" value="{{ $tagValue }}">
                    @endforeach

                    <select class="uq-control uq-sort" name="sort" data-auto-submit aria-label="排序方式">
                        <option value="" @selected(request('sort') !== 'price-asc')>排序：預設</option>
                        <option value="price-asc" @selected(request('sort') === 'price-asc')>排序：價格由低到高</option>
                    </select>
                    {{-- 沒有 JavaScript 時這顆是唯一的出路，有的時候 master 版型會把它藏起來 --}}
                    <button class="uq-control" type="submit" data-auto-submit-fallback>套用</button>
                </form>

                @include('partials.tag-filter-button')
            </x-slot:end>
        </x-toolbar>

        @include('partials.tag-filter')

        @include('lists.cards', ['hmallProductList' => $hmallProductList])
    </div>
@endsection
