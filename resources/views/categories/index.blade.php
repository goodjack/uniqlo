@extends('layouts.master')

@php
    use App\Enums\Brand;

    $currentUrl = url()->current();

    // 兩家的分類名稱會撞（UNIQLO 的「女裝」與 GU 的「WOMEN」），錨點要連品牌一起編
    $anchorFor = fn($group) => "group-{$group['brand']->slug()}-{$group['category']->code}";
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

    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container">
            {{-- 十一個群組一路捲下去很容易迷路，選單留在畫面上當定位器 --}}
            @include('partials.section-menu', [
                'id' => 'group_menu',
                'items' => $groups
                    ->map(
                        fn($group) => [
                            'anchor' => $anchorFor($group),
                            'label' => $group['category']->name,
                        ],
                    )
                    ->all(),
            ])

            @foreach ($groups as $group)
                @include('partials.section-anchor', ['anchor' => $anchorFor($group), 'menu' => 'group_menu'])

                <div class="uq-category-group">
                    <h2 class="unstyled uq-h2">
                        {{ $group['category']->name }}
                        {{-- 品牌顏色跟商品卡片右上角那顆一致：UNIQLO 紅、GU 藍 --}}
                        <div class="ts mini @if ($group['brand'] === Brand::Gu) info @else negative @endif label">
                            {{ $group['brand']->value }}
                        </div>
                        <span class="uq-count uq-count-end">{{ count($group['children']) }} 個分類</span>
                    </h2>

                    <div class="uq-pill-row">
                        @foreach ($group['children'] as $child)
                            <a class="uq-control uq-pill uq-pill-small"
                                href="{{ route('categories.show', ['brand' => $group['brand']->slug(), 'code' => $child->code]) }}">
                                {{ $child->name }}
                                <span class="uq-count">{{ $child->hmall_products_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
