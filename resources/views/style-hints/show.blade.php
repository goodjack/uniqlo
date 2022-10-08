@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')
@extends('layouts.master')

@php
$shareText = $hmallProductPresenter->getFullName($hmallProduct) . ' | UNIQLO 比價 | UQ 搜尋';
$currentUrl = url()->current();
@endphp

@section('title', "{$hmallProductPresenter->getFullName($hmallProduct)} 的 StyleHint 網友穿搭靈感")

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    <meta name="description" content="共 {{ $styleHints->total() }} 張 StyleHint 網友穿搭靈感" />
    <meta property="og:type" content="og:product" />
    <meta property="og:title" content="{{ $hmallProductPresenter->getFullName($hmallProduct) }} 的 StyleHint 網友穿搭靈感 | UQ 搜尋" />
    <meta property="og:url" content="{{ $currentUrl }}" />
    <meta property="og:description" content="共 {{ $styleHints->total() }} 張 StyleHint 網友穿搭靈感" />
    <meta property="og:image" content="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:creator" content="@littlegoodjack" />
    <meta name="twitter:title"
        content="{{ $hmallProductPresenter->getFullName($hmallProduct) }} 的 StyleHint 網友穿搭靈感 | UQ 搜尋" />
    <meta name="twitter:description" content="共 {{ $styleHints->total() }} 張 StyleHint 網友穿搭靈感" />
    <meta name="twitter:image" content="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}" />
@endsection

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css"
        integrity="sha512-ZKX+BvQihRJPA8CROKBhDNvoc2aDMOdAlcm7TUQY+35XYtrd3yh95QOOhsPDQY9QnKE0Wqag9y38OIgEvb88cA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="camera retro faded icon"></i>
        <h1 class="header">{{ $hmallProductPresenter->getFullName($hmallProduct) }} 的 StyleHint 網友穿搭靈感</h1>
        <span class="description">共 {{ $styleHints->total() }} 張 StyleHint 網友穿搭靈感</span>
        <div class="action">
            <a class="ts mini basic button" href="{{ $hmallProduct->route_url }}">返回商品頁</a>
        </div>
    </div>
    <div class="ts very padded horizontally fitted attached fluid secondary segment">
        <div class="ts container">
            <div class="ts items">
                <div class="item">
                    <div class="ts mini image">
                        <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
                            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
                    </div>
                    <div class="middle aligned content">
                        <div class="header">
                            {{ $hmallProductPresenter->getFullName($hmallProduct) }}
                        </div>
                        <div class="inline middoted meta">
                            <span>{{ $hmallProduct->brand }} 商品編號 {{ $hmallProduct->code }}
                                {{ $hmallProduct->product_code }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ts doubling four flatted cards">
                @foreach ($styleHints as $key => $styleHint)
                    <x-image-card link="{{ $styleHint->official_site_url }}" imageUrl="{{ $styleHint->image_url }}"
                        largeImageUrl="{{ $styleHint->large_image_url }}"
                        alt="StyleHint 網友穿搭靈感 {{ $styleHints->firstItem() + $key }} ({{ $styleHint->user_name }})"
                        width="720" height="960" />
                @endforeach
            </div>
        </div>
    </div>
    <div class="ts very padded horizontally fitted attached fluid secondary center aligned segment">
        <div class="ts small buttons">
            {{-- Previous Page Link --}}
            @if ($styleHints->onFirstPage())
                <a class="ts icon disabled button" aria-disabled="true" aria-label="@lang('pagination.previous')">
                    <i class="left chevron icon"></i>
                </a>
            @else
                <a class="ts icon button" href="{{ $styleHints->previousPageUrl() }}" rel="prev"
                    aria-label="@lang('pagination.previous')">
                    <i class="left chevron icon"></i>
                </a>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <a class="ts icon disabled button" aria-disabled="true">{{ $element }}</a>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $styleHints->currentPage())
                            <a class="ts icon active button" href="{{ $url }}" aria-current="page">
                                {{ $page }}
                            </a>
                        @else
                            <a class="ts icon button" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($styleHints->hasMorePages())
                <a class="ts icon button" href="{{ $styleHints->nextPageUrl() }}" rel="next"
                    aria-label="@lang('pagination.next')">
                    <i class="right chevron icon"></i>
                </a>
            @else
                <a class="ts icon disabled button" aria-disabled="true" aria-label="@lang('pagination.next')">
                    <i class="right chevron icon"></i>
                </a>
            @endif
        </div>
    </div>
@endsection

@section('javascript')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox-plus-jquery.min.js"
        integrity="sha512-6gudNVbNM/cVsLUMOb8g2b/RBqtQJ3aDfRFgU+5paeaCTtbYY/Dg00MzZq7r6RvJGI2KKtPBhjkHGTL/iOe21A=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script>
        'use strict';

        lightbox.option({
            'alwaysShowNavOnTouchDevices': true,
            'disableScrolling': true,
            'fadeDuration': 150,
            'resizeDuration': 150,
            'imageFadeDuration': 0,
            'showImageNumberLabel': false,
        });
    </script>
@endsection
