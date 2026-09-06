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
    $sortText = request('sort') === \App\Services\ListService::SORT_PRICE_ASC ? '依價格由低到高排序' : $sortSummary;

    /*
     * 章節選單跟正文共用同一份「有商品的性別」清單，抬到這裡算一次就好；
     * 也才能讓選單站在 container 外面、正文站在另一個 container 裡面
     * （見下面 section-menu 的說明）。
     */
    $genders = ['men' => '男裝', 'women' => '女裝', 'kids' => '童裝', 'baby' => '嬰幼兒'];
    $availableGenders = collect($genders)->filter(fn ($label, $key) => count($hmallProductList[$key]) > 0);
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
                        <option value="" @selected(request('sort') !== \App\Services\ListService::SORT_PRICE_ASC)>排序：預設</option>
                        <option value="{{ \App\Services\ListService::SORT_PRICE_ASC }}" @selected(request('sort') === \App\Services\ListService::SORT_PRICE_ASC)>排序：價格由低到高</option>
                    </select>
                    {{-- 沒有 JavaScript 時這顆是唯一的出路，有的時候 master 版型會把它藏起來 --}}
                    <button class="uq-control" type="submit" data-auto-submit-fallback>套用</button>
                </form>

                @include('partials.tag-filter-button')
            </x-slot:end>
        </x-toolbar>

        @include('partials.tag-filter')
    </div>

    @unless ($availableGenders->isEmpty())
        {{-- 性別是這一頁的章節，選單用跟商品頁、分類總覽同一個 partial。它自己 --}}
        {{-- 站在上面那個 container 外面，白底跟底線才是滿版、不是只跨中間那欄 --}}
        @include('partials.section-menu', [
            'id' => 'gender_menu',
            'items' => $availableGenders
                ->map(fn ($label, $key) => [
                    'anchor' => $key,
                    'label' => $label,
                    'count' => count($hmallProductList[$key]),
                ])
                ->values()
                ->all(),
        ])
    @endunless

    <div class="ts container">
        @include('lists.cards', [
            'hmallProductList' => $hmallProductList,
            'availableGenders' => $availableGenders,
        ])
    </div>
@endsection
