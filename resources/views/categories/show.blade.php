@extends('layouts.master')

@php
    use App\Enums\CategoryLevel;
    use App\Support\Breadcrumb;

    $currentUrl = url()->current();
    $title = "{$category->name}（{$hmallProducts->total()} 件）";

    $categoryUrl = fn($code) => route('categories.show', ['brand' => $brand->slug(), 'code' => $code]);

    /*
     * 從 SEO 進來的人需要知道自己在整棵分類樹的哪裡，也給分類頁彼此建立內部連結。
     * 頂層沒有自己的頁面，只當文字，並且標上品牌——兩家的分類名稱會撞
     * （UNIQLO 的「男裝」與 GU 的「MEN」都是男裝）。
     */
    $crumbs = array_merge(
        [Breadcrumb::home(), Breadcrumb::link('categories')],
        $breadcrumb
            ->map(
                fn($crumb) => $crumb->level === CategoryLevel::Top
                    ? Breadcrumb::text("{$brand->value} {$crumb->name}")
                    : ['label' => $crumb->name, 'url' => $categoryUrl($crumb->code)],
            )
            ->all(),
    );
@endphp

@section('title', $category->name)

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    <meta name="description" content="{{ $category->name }} 的 UNIQLO 與 GU 商品比價 | UQ 搜尋" />
    <meta property="og:title" content="{{ $category->name }} | UQ 搜尋" />
    <meta property="og:url" content="{{ $currentUrl }}" />
    <meta property="og:description" content="{{ $title }} | UNIQLO 比價 | UQ 搜尋" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="tags faded icon"></i>
        <span class="header">{{ $category->name }}</span>
        {{-- 分類不提供品牌篩選：兩家的分類 code 各成一套，一個分類只會有一家的商品 --}}
        <span class="description">
            <div class="ts mini @if ($brand === \App\Enums\Brand::Gu) info @else negative @endif label">
                {{ $brand->value }}
            </div>
            {{ $hmallProducts->total() }} 件
        </span>
    </div>

    <div class="ts container uq-page">
        @include('partials.breadcrumb', ['crumbs' => $crumbs])

        <x-toolbar>
            @if ($children->isNotEmpty())
                <x-slot:start>
                    {{-- 往下鑽的入口，不是篩選條件，一行文字連結就夠——pill 太搶戲 --}}
                    <div class="uq-cat-list">
                        @foreach ($children as $child)
                            <a href="{{ $categoryUrl($child->code) }}">
                                {{ $child->name }}
                                <span class="uq-count">{{ $child->hmall_products_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </x-slot:start>
            @endif

            <x-slot:end>
                @include('partials.tag-filter-button')
            </x-slot:end>
        </x-toolbar>

        @include('partials.tag-filter')

        {{-- 篩到 0 件時整個卡片容器與分頁都不該出現，只留一句話說明現在的狀況 --}}
        @if ($hmallProducts->isEmpty())
            @include('partials.empty-state', [
                'icon' => 'search faded',
                'title' => '沒有符合的商品',
                'hint' => '試試看少選幾個條件',
            ])
        @else
            {{-- 分類本來就是單一性別的軸（男裝上衣），不像清單頁需要拆成四段 --}}
            <div class="ts doubling link cards four">
                @each('hmall-products.card', $hmallProducts, 'hmallProduct')
            </div>

        @include('partials.pagination', ['paginator' => $hmallProducts])
        @endif
    </div>
@endsection
