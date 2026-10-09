@extends('layouts.master')

@php
    $currentUrl = url()->current();

    // 兩家的分類名稱會撞（UNIQLO 的「女裝」與 GU 的「WOMEN」），錨點要連品牌一起編
    $anchorFor = fn($group) => "group-{$group['brand']->slug()}-{$group['category']->code}";

    // 兩家的分類樹各自獨立，照品牌切成兩塊，不要求使用者自己認品牌標籤
    $brandBlocks = $groups->groupBy(fn($group) => $group['brand']->value);

    // 章節選單也照品牌分兩段，品牌名是小標、不是連結
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

    @include('partials.section-menu', ['id' => 'group_menu', 'items' => $menuItems])

    {{-- 選單到第一個標題的距離由這層 segment 負責；放進 container 裡會被 Tocas 的 :first-child 規則歸零 --}}
    <div class="ts basic horizontally fitted segment">
        <div class="ts container">
            @foreach ($brandBlocks as $brandName => $brandGroups)
                <h2 class="ts large dividing header uq-brand-h2">{{ $brandName }}</h2>

                @foreach ($brandGroups as $group)
                    @include('partials.section-anchor', ['anchor' => $anchorFor($group), 'menu' => 'group_menu'])

                    <h3 class="ts header">
                        {{ $group['category']->name }}
                        <div class="inline sub header">{{ count($group['children']) }} 個分類</div>
                    </h3>

                    <div class="ts horizontal middoted list uq-cat-list">
                        @foreach ($group['children'] as $child)
                            <a class="item"
                                href="{{ \App\Support\Url::category($group['brand'], $child->code) }}">
                                {{ $child->name }}
                                <div class="ts tiny circular label">{{ $child->hmall_products_count }}</div>
                            </a>
                        @endforeach
                    </div>

                    <div class="ts hidden section divider"></div>
                @endforeach
            @endforeach
        </div>
    </div>
@endsection
