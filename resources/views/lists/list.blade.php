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

@section('css')
    <style>
        /*
         * 只有這一頁有黏在 nav 下面的性別選單，所以錨點跳轉要多讓開選單本身的
         * 高度，否則跳過去的標題會被它蓋住（全站預設的 scroll-padding-top 只
         * 避開了 nav）。選單的樣式本身跨頁共用，在 public/css/app.css。
         */
        :root {
            --gender-menu-height: 41px;
        }

        html {
            scroll-padding-top: calc(var(--uq-nav-height) + var(--gender-menu-height) + 12px);
        }
    </style>
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="{{ $typeStyle }} {{ $typeIcon }} icon"></i>
        <span class="header">{{ $count }} 件{{ $typeName }}</span>
        @isset($description)
            <span class="description">{!! $description !!}</span>
        @endisset
    </div>

    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container">
            <x-toolbar>
                <x-slot:start>
                    <div class="ts small basic buttons">
                        <a class="ts button {{ $currentBrand === null ? 'active' : '' }}"
                            href="{{ $currentUrl }}?{{ $queryFor(['brand' => null]) }}">全部</a>
                        <a class="ts button {{ $currentBrand === 'UNIQLO' ? 'active' : '' }}"
                            href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'UNIQLO']) }}">UNIQLO</a>
                        <a class="ts button {{ $currentBrand === 'GU' ? 'active' : '' }}"
                            href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'GU']) }}">GU</a>
                    </div>
                </x-slot:start>

                <x-slot:end>
                    {{-- Tocas 的 basic dropdown 就是套了樣式的原生 <select>，不吃 JavaScript --}}
                    <form method="GET" action="{{ $currentUrl }}" class="uq-sort-form">
                        @if ($currentBrand)
                            <input type="hidden" name="brand" value="{{ $currentBrand }}">
                        @endif
                        @foreach ($selectedTagValues as $tagValue)
                            <input type="hidden" name="tags[]" value="{{ $tagValue }}">
                        @endforeach

                        <select class="ts small basic dropdown" name="sort" data-auto-submit aria-label="排序方式">
                            <option value="" @selected(request('sort') !== 'price-asc')>排序：預設</option>
                            <option value="price-asc" @selected(request('sort') === 'price-asc')>排序：價格由低到高</option>
                        </select>
                        {{-- 沒有 JavaScript 時這顆是唯一的出路，有的時候 master 版型會把它藏起來 --}}
                        <button class="ts small basic button" type="submit" data-auto-submit-fallback>套用</button>
                    </form>

                    @include('partials.tag-filter-button')
                </x-slot:end>

                @include('partials.tag-filter')
            </x-toolbar>

            @include('lists.cards', ['hmallProductList' => $hmallProductList])
        </div>
    </div>
@endsection
