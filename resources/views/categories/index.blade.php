@extends('layouts.master')

@php
    $currentUrl = url()->current();

    // 兩家的分類名稱會撞（UNIQLO 的「女裝」與 GU 的「WOMEN」），錨點要連品牌一起編
    $anchorFor = fn($group) => "group-{$group['brand']->slug()}-{$group['category']->code}";

    /*
     * getOverview() 已經排成「UNIQLO 全部、再 GU 全部」，這裡照品牌切成兩塊。
     * 兩家的分類樹是各自獨立的一套，混在同一串裡看等於要求使用者自己認品牌標籤。
     */
    $brandBlocks = $groups->groupBy(fn($group) => $group['brand']->value);

    // 章節選單也照品牌分兩段：每一段前面掛品牌名，它不是連結、不進 scrollspy
    $menuItems = $brandBlocks
        ->flatMap(
            fn($brandGroups, $brandName) => collect([['heading' => $brandName]])->concat(
                $brandGroups->map(
                    fn($group) => [
                        'anchor' => $anchorFor($group),
                        'label' => $group['category']->name,
                    ],
                ),
            ),
        )
        ->all();
@endphp

@section('title', '商品分類')

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    <meta name="description" content="依商品分類瀏覽 UNIQLO 與 GU 的商品與價格 | UQ 搜尋" />
    <meta property="og:title" content="商品分類 | UQ 搜尋" />
    <meta property="og:url" content="{{ $currentUrl }}" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="sitemap faded icon"></i>
        <span class="header">商品分類</span>
        <span class="description">只列出目前還買得到商品的分類</span>
    </div>

    {{-- 十個群組一路捲下去很容易迷路，選單留在畫面上當定位器。這個 partial --}}
    {{-- 自己站在 container 外面、自帶內層 container，白底跟底線才是滿版 --}}
    @include('partials.section-menu', ['id' => 'group_menu', 'items' => $menuItems])

    <div class="ts container">
        @foreach ($brandBlocks as $brandName => $brandGroups)
            {{-- 品牌區塊的標題。顏色跟商品卡片右上角那顆角標一致：UNIQLO 紅、GU 藍 --}}
            <h2 class="unstyled uq-h2 uq-brand-h2 uq-brand-{{ strtolower($brandName) }}">{{ $brandName }}</h2>

            @foreach ($brandGroups as $group)
                @include('partials.section-anchor', ['anchor' => $anchorFor($group), 'menu' => 'group_menu'])

                <div class="uq-category-group">
                    {{-- 群組標題不再各自標品牌，區塊標題已經說了 --}}
                    <h3 class="unstyled uq-h3">
                        {{ $group['category']->name }}
                        <span class="uq-count uq-count-end">{{ count($group['children']) }} 個分類</span>
                    </h3>

                    {{-- pill 太搶戲：這裡是往下鑽的入口，不是需要勾選的篩選條件，一行文字連結就夠 --}}
                    <div class="uq-cat-list">
                        @foreach ($group['children'] as $child)
                            <a href="{{ route('categories.show', ['brand' => $group['brand']->slug(), 'code' => $child->code]) }}">
                                {{ $child->name }}
                                <span class="uq-count">{{ $child->hmall_products_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
@endsection
