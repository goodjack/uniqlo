@extends('layouts.master')

@php
    $currentUrl = url()->current();
    $title = "{$count} 件{$typeName}";
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
    @include('partials.list-type-icon-styles')
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="{{ $typeStyle }} {{ $typeIcon }} icon"></i>
        <span class="header">{{ $count }} 件{{ $typeName }}</span>
        <span class="description">
            @isset($description)
                {!! $description !!}
                <div class="ts hidden divider"></div>
            @endisset
            @php
                // 品牌與排序是兩個獨立的軸，切換其中一個要保留另一個
                $queryFor = fn (array $changes) => http_build_query(
                    array_filter(array_merge(request()->only(['brand', 'sort']), $changes))
                );
            @endphp
            <div class="ts small very compact buttons">
                <a class="ts button {{ !in_array(request('brand'), ['UNIQLO', 'GU']) ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['brand' => null]) }}">全部</a>
                <a class="ts button {{ request('brand') == 'UNIQLO' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'UNIQLO']) }}">UNIQLO</a>
                <a class="ts button {{ request('brand') == 'GU' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'GU']) }}">GU</a>
            </div>
            <div class="ts hidden divider"></div>
            <div class="ts small very compact buttons">
                <a class="ts button {{ request('sort') !== 'price-asc' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['sort' => null]) }}">預設排序</a>
                <a class="ts button {{ request('sort') === 'price-asc' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['sort' => 'price-asc']) }}">價格由低到高</a>
            </div>
        </span>
    </div>

    @include('lists.cards', ['hmallProductList' => $hmallProductList])
@endsection
