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
    <style>
        i.most-reviewed.icon {
            color: #885A89 !important;
        }

        i.top-wearing.icon {
            color: #F0C8AF !important;
        }

        i.coming-soon.icon {
            color: #50723C !important;
        }

        i.online-special.icon {
            color: #F29E18 !important;
        }

        i.most-visited.icon {
            color: #B58105 !important;
        }
    </style>
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
            <div class="ts small very compact buttons">
                <a class="ts button {{ !in_array(request('brand'), ['UNIQLO', 'GU']) ? 'active' : '' }}"
                    href="{{ $currentUrl }}">全部</a>
                <a class="ts button {{ request('brand') == 'UNIQLO' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?brand=UNIQLO">UNIQLO</a>
                <a class="ts button {{ request('brand') == 'GU' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?brand=GU">GU</a>
            </div>
        </span>
    </div>

    @include('lists.cards', ['hmallProductList' => $hmallProductList])
@endsection
