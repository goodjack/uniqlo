@extends('layouts.master')

@php
    use App\Enums\CategoryLevel;
    use App\Support\Breadcrumb;

    $currentUrl = url()->current();
    // 每一頁的 canonical 指向自己（第 1 頁不帶 page），篩選參數不算另一頁
    $canonicalUrl = $hmallProducts->currentPage() > 1
        ? $currentUrl.'?page='.$hmallProducts->currentPage()
        : $currentUrl;
    $title = "{$category->name}（{$hmallProducts->total()} 件）";

    $categoryUrl = fn($code) => route('categories.show', ['brand' => $brand->slug(), 'code' => $code]);

    $queryFor = fn(array $changes) => http_build_query(
        array_filter(array_merge(request()->only(['tags', 'q']), $changes))
    );

    $selectedTagValues = collect(\App\Enums\ProductTag::fromValues((array) request('tags', [])))
        ->map->value
        ->all();
    $currentQ = (string) request('q');

    // 頂層沒有自己的頁面，只當文字並標上品牌：兩家的頂層名稱會撞
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
    <link rel="canonical" href="{{ $canonicalUrl }}" />
    @if ($currentQ !== '')
        <meta name="robots" content="noindex, follow" />
    @endif
    <meta name="description" content="{{ $category->name }} 的 UNIQLO 與 GU 商品比價 | UQ 搜尋" />
    <meta property="og:title" content="{{ $category->name }} | UQ 搜尋" />
    <meta property="og:url" content="{{ $canonicalUrl }}" />
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

    <div class="ts container">
        @include('partials.breadcrumb', ['crumbs' => $crumbs])

        @if ($children->isNotEmpty())
            {{-- 子分類是往別頁走的連結、不是控制項，所以放在工具列外面 --}}
            <div class="ts horizontal middoted list uq-cat-list">
                @foreach ($children as $child)
                    <a class="item" href="{{ $categoryUrl($child->code) }}">
                        {{ $child->name }}
                        <div class="ts tiny circular label">{{ $child->hmall_products_count }}</div>
                    </a>
                @endforeach
            </div>
        @endif

        <x-toolbar>
            <x-slot:end>
                {{-- 不掛即時篩（data-instant-filter）：分類頁有分頁，前端只篩得到這一頁，會誤以為篩了整個分類 --}}
                <form method="GET" action="{{ $currentUrl }}" class="ts input uq-search-form">
                    @foreach ($selectedTagValues as $tagValue)
                        <input type="hidden" name="tags[]" value="{{ $tagValue }}">
                    @endforeach

                    <i class="search icon" aria-hidden="true"></i>
                    <input type="search" name="q" class="uq-search-input" value="{{ $currentQ }}"
                        placeholder="在這個分類裡找…" maxlength="50" aria-label="在這個分類裡找">
                    @if ($currentQ !== '')
                        <a class="uq-search-clear" href="{{ $currentUrl }}?{{ $queryFor(['q' => null]) }}"
                            aria-label="清除搜尋">&times;</a>
                    @endif
                </form>

                @include('partials.tag-filter-button')
            </x-slot:end>
        </x-toolbar>

        @include('partials.tag-filter')

        @if ($hmallProducts->isEmpty())
            @include('partials.empty-state', [
                'icon' => 'search faded',
                'title' => $currentQ !== '' ? "沒有符合「{$currentQ}」的商品" : '沒有符合的商品',
                'hint' => $currentQ !== '' ? '試試看換個關鍵字，或少選幾個條件' : '試試看少選幾個條件',
            ])
        @else
            <div class="ts doubling cards four uq-product-cards">
                @each('hmall-products.card', $hmallProducts, 'hmallProduct')
            </div>

        @include('partials.pagination', ['paginator' => $hmallProducts])
        <div class="ts hidden divider"></div>
        @endif
    </div>
@endsection

@section('javascript')
    <script src="{{ asset('js/list-search.js') }}?v={{ filemtime(public_path('js/list-search.js')) }}"></script>
@endsection
