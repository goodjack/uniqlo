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

    {{--
        章節選單自己不留下方 margin（見 app.css 的 .uq-section-menu），選單到第一個
        品牌標題的距離改由這一層 Tocas segment 負責，做法跟清單頁同一套：basic 去掉
        框線與底色、horizontally fitted 去掉左右 padding 交給裡面的 container，剩下
        上下各 1em padding 加 1rem margin。包在 container 外面不是裡面：Tocas 有
        .ts.segment:first-child{margin-top:0}，擺進去就會被歸零、只剩一半。
    --}}
    <div class="ts basic horizontally fitted segment">
        <div class="ts container">
            @foreach ($brandBlocks as $brandName => $brandGroups)
                {{-- 品牌區塊的標題，用 Tocas 的 ts large dividing header --}}
                <h2 class="ts large dividing header uq-brand-h2">{{ $brandName }}</h2>

                @foreach ($brandGroups as $group)
                    @include('partials.section-anchor', ['anchor' => $anchorFor($group), 'menu' => 'group_menu'])

                    {{-- 群組標題不再各自標品牌，區塊標題已經說了 --}}
                    <h3 class="ts header">
                        {{ $group['category']->name }}
                        <div class="inline sub header">{{ count($group['children']) }} 個分類</div>
                    </h3>

                    {{-- 往下鑽的入口，不是需要勾選的篩選條件，用 Tocas 的橫向中點清單 --}}
                    <div class="ts horizontal middoted list uq-cat-list">
                        @foreach ($group['children'] as $child)
                            <a class="item"
                                href="{{ route('categories.show', ['brand' => $group['brand']->slug(), 'code' => $child->code]) }}">
                                {{ $child->name }}
                                <div class="ts mini circular label">{{ $child->hmall_products_count }}</div>
                            </a>
                        @endforeach
                    </div>

                    {{-- 群組之間用 Tocas 的隱形分隔線隔開，跟 master 的清單頁同一個做法 --}}
                    <div class="ts hidden section divider"></div>
                @endforeach
            @endforeach
        </div>
    </div>
@endsection
