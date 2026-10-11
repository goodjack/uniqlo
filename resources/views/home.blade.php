@extends('layouts.master')

@php
    $homeTitle = \App\Support\SiteTitle::NAME.'｜UNIQLO 與 GU 價格查詢比價';
    $homeDescription = '查 UNIQLO 與 GU 商品的歷史價格、限時特價與新品，比較台灣與日本售價，看網友穿搭。';
@endphp

@section('title', '首頁')
@section('full-title', $homeTitle)

@section('json-ld')
    @include('partials.json-ld', [
        'data' => [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => \App\Support\SiteTitle::NAME,
            'alternateName' => \App\Support\SiteTitle::TAGLINE,
            'url' => route('home'),
        ],
    ])
@endsection

@section('metadata')
    <link rel="canonical" href="{{ route('home') }}" />
    <meta name="description" content="{{ $homeDescription }}" />
    <meta property="og:url" content="{{ route('home') }}" />
    <meta property="og:description" content="{{ $homeDescription }}" />
    <meta property="og:image" content="{{ asset('icons/icon-512x512.png') }}" />
    <meta name="twitter:card" content="summary" />
@endsection

@section('content')
    <div class="ts very padded horizontally fitted attached fluid segment">
        <div class="ts very narrow container">
            <div class="ts hidden divider"></div>
            <div class="ts hidden divider"></div>
            <div class="ts hidden divider"></div>
            <h1 class="ts center aligned header">
                <i class="big fitted negative clone icon"></i>
                &nbsp;
                UQ 搜尋
            </h1>
            <div class="ts hidden divider"></div>
            <div class="ts hidden divider"></div>
            <form class="ts big form" action="{{ route('search.index') }}">
                <input name="query" class="ts fluid input" inputmode="search" placeholder="輸入關鍵字，或 UNIQLO/GU 商品編號">
                <div class="ts hidden divider"></div>
                <center>
                    <button class="ts button" type="submit">搜尋</button>
                </center>
            </form>
            <div class="ts hidden divider"></div>
        </div>
    </div>

    @foreach ($sections as $section)
        @include('home.partials.product-row', ['section' => $section])
    @endforeach
@endsection
