@extends('layouts.master')

@php
    $currentUrl = url()->current();
    // <title> 與社群描述沿用 master 的句型（「65 件商品期間限定特價中」）
    $title = "{$count} 件{$titleName}";

    // 品牌、排序、標籤與關鍵字是四個獨立的軸，切換其中一個要保留另外三個
    $queryFor = fn(array $changes) => http_build_query(
        array_filter(array_merge(request()->only(['brand', 'sort', 'tags', 'q']), $changes))
    );

    // ListRequest 已經丟掉不合法的品牌、排序、標籤與關鍵字，這裡讀到的都是乾淨的值
    $currentBrand = request('brand');
    $sortByPrice = request('sort') === \App\Services\ListService::SORT_PRICE_ASC;
    $selectedTagValues = (array) request('tags', []);
    $currentQ = (string) request('q');
    $sortText = $sortByPrice ? '依價格由低到高排序' : $sortSummary;

    // 四個性別段一律都列，「男裝 0 件」本身就是資訊；章節選單與正文共用這一份
    $genders = collect(['men' => '男裝', 'women' => '女裝', 'kids' => '童裝', 'baby' => '嬰幼兒']);
@endphp

@section('title', $title)

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    @if ($currentQ !== '')
        {{-- 帶關鍵字的結果是既有清單的重組，不需要另外被索引 --}}
        <meta name="robots" content="noindex, follow" />
    @endif
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
        {{-- list-search.js 即時篩之後會用 data-sort-text 重組這句話 --}}
        <span class="description" data-instant-subtitle data-sort-text="{{ $sortText }}">
            @if ($currentQ !== '')
                {{ $count }} 件符合「{{ $currentQ }}」，{{ $sortText }}
            @else
                {{ $count }} 件，{{ $sortText }}
            @endif
        </span>
    </div>

    <div class="ts container">
        <x-toolbar>
            <x-slot:start>
                {{-- 不加 basic：Tocas 的 basic 按鈕群選中狀態對比只有 1.3:1，看不出選了哪一顆 --}}
                <div class="ts compact buttons" data-keeps-q>
                    <a class="ts button {{ $currentBrand === null ? 'active' : '' }}"
                        href="{{ $currentUrl }}?{{ $queryFor(['brand' => null]) }}">全部</a>
                    <a class="ts button {{ $currentBrand === 'UNIQLO' ? 'active' : '' }}"
                        href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'UNIQLO']) }}">UNIQLO</a>
                    <a class="ts button {{ $currentBrand === 'GU' ? 'active' : '' }}"
                        href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'GU']) }}">GU</a>
                </div>
            </x-slot:start>

            <x-slot:end>
                <form method="GET" action="{{ $currentUrl }}" class="ts input uq-search-form">
                    @if ($currentBrand)
                        <input type="hidden" name="brand" value="{{ $currentBrand }}">
                    @endif
                    @if ($sortByPrice)
                        <input type="hidden" name="sort" value="{{ \App\Services\ListService::SORT_PRICE_ASC }}">
                    @endif
                    @foreach ($selectedTagValues as $tagValue)
                        <input type="hidden" name="tags[]" value="{{ $tagValue }}">
                    @endforeach

                    <i class="search icon" aria-hidden="true"></i>
                    <input type="search" name="q" class="uq-search-input" data-instant-filter
                        value="{{ $currentQ }}" placeholder="在這個清單裡找…" maxlength="50" aria-label="在這個清單裡找">
                    @if ($currentQ !== '')
                        <a class="uq-search-clear" href="{{ $currentUrl }}?{{ $queryFor(['q' => null]) }}"
                            aria-label="清除搜尋">&times;</a>
                    @endif
                </form>

                {{-- 原生 <select> 套 Tocas 的 .ts.basic.dropdown，沒有 JavaScript 也能用 --}}
                <form method="GET" action="{{ $currentUrl }}" class="uq-sort-form" data-keeps-q>
                    @if ($currentBrand)
                        <input type="hidden" name="brand" value="{{ $currentBrand }}">
                    @endif
                    @foreach ($selectedTagValues as $tagValue)
                        <input type="hidden" name="tags[]" value="{{ $tagValue }}">
                    @endforeach
                    @if ($currentQ !== '')
                        <input type="hidden" name="q" value="{{ $currentQ }}">
                    @endif

                    <select class="ts basic dropdown" name="sort" data-auto-submit aria-label="排序方式">
                        <option value="" @selected(! $sortByPrice)>排序：預設</option>
                        <option value="{{ \App\Services\ListService::SORT_PRICE_ASC }}" @selected($sortByPrice)>排序：價格由低到高</option>
                    </select>
                    <button class="ts basic compact button" type="submit" data-auto-submit-fallback>套用</button>
                </form>

                @include('partials.tag-filter-button')
            </x-slot:end>
        </x-toolbar>

        @include('partials.tag-filter')
    </div>

    @if ($count > 0)
        {{-- 選單放在 container 外面，白底與底線才會滿版 --}}
        @include('partials.section-menu', [
            'id' => 'gender_menu',
            'items' => $genders
                ->map(fn ($label, $key) => [
                    'anchor' => $key,
                    'label' => $label,
                    'count' => count($hmallProductList[$key]),
                ])
                ->values()
                ->all(),
        ])
    @endif

    {{-- 這層 segment 負責選單到第一段的間距；放進 container 裡會被 Tocas 的
         .ts.segment:first-child { margin-top: 0 } 歸零 --}}
    <div class="ts basic horizontally fitted segment">
        <div class="ts container">
            @include('lists.cards', [
                'hmallProductList' => $hmallProductList,
                'genders' => $genders,
                'count' => $count,
            ])
        </div>
    </div>
@endsection

@section('javascript')
    <script src="{{ asset('js/list-search.js') }}?v={{ filemtime(public_path('js/list-search.js')) }}"></script>
@endsection
