@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')
@extends('layouts.master')

@section('title', "{$hmallProductPresenter->getFullName($hmallProduct)} 的網友穿搭")

@section('metadata')
@endsection

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css"
        integrity="sha512-ZKX+BvQihRJPA8CROKBhDNvoc2aDMOdAlcm7TUQY+35XYtrd3yh95QOOhsPDQY9QnKE0Wqag9y38OIgEvb88cA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="camera retro faded icon"></i>
        <span class="header">{{ $hmallProductPresenter->getFullName($hmallProduct) }} 的網友穿搭</span>
        <span class="description">共 {{ $styleHints->total() }} 張</span>
    </div>
    <div class="ts very padded horizontally fitted attached fluid secondary segment">
        <div class="ts container">
            <div class="ts doubling four flatted cards">
                @foreach ($styleHints as $key => $styleHint)
                    <x-image-card link="{{ $styleHint->original_source_url }}" imageUrl="{{ $styleHint->image_url }}"
                        largeImageUrl="{{ $styleHint->large_image_url }}"
                        alt="網友穿搭 {{ $styleHints->firstItem() + $key }} ({{ $styleHint->user_info['name'] }})"
                        width="720" height="960" />
                @endforeach
            </div>
        </div>
    </div>
    <div class="ts very padded horizontally fitted attached fluid secondary center aligned segment">
        <div class="ts small buttons">
            {{-- Previous Page Link --}}
            @if ($styleHints->onFirstPage())
                <a class="ts disable icon button">
                    <i class="left chevron icon"></i>
                </a>
            @else
                <a class="ts icon button" href="{{ $styleHints->previousPageUrl() }}">
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
