@extends('layouts.master')

@php
    use App\Enums\CategoryLevel;
    use App\Support\Breadcrumb;

    $currentUrl = url()->current();
    $title = "{$category->name}（{$hmallProducts->total()} 件）";

    $categoryUrl = fn($code) => route('categories.show', ['brand' => $brand->slug(), 'code' => $code]);

    // 分類頁沒有品牌、排序這兩個軸，能帶著走的只有標籤跟搜尋關鍵字
    $queryFor = fn(array $changes) => http_build_query(
        array_filter(array_merge(request()->only(['tags', 'q']), $changes))
    );

    $selectedTagValues = collect(\App\Enums\ProductTag::fromValues((array) request('tags', [])))
        ->map->value
        ->all();
    // ListRequest::prepareForValidation() 已經修剪並截斷過，這裡直接讀就是乾淨的值
    $currentQ = (string) request('q');

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
    @if ($currentQ !== '')
        {{-- 帶關鍵字的分類頁是既有分類的重組，不需要另外被索引，做法照搜尋結果頁 --}}
        <meta name="robots" content="noindex, follow" />
    @endif
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
            <x-slot:start>
                @if ($children->isNotEmpty())
                    {{-- 往下鑽的入口，不是篩選條件，一行文字連結就夠——pill 太搶戲 --}}
                    <div class="uq-cat-list">
                        @foreach ($children as $child)
                            <a href="{{ $categoryUrl($child->code) }}">
                                {{ $child->name }}
                                <span class="uq-count">{{ $child->hmall_products_count }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                {{--
                    在這個分類裡找：分類頁是資料庫分頁查詢
                    （HmallProductRepository::getProductsByCategoryId()），q 進 SQL
                    篩的是整個分類，不是畫面上這一頁載入到的商品，所以輸入框不掛
                    list-search.js 用來啟用即時篩的 data-instant-filter（前端即時篩
                    只能篩到已經渲染出來的這一頁，誤導使用者以為篩了整個分類——
                    站主試用後的裁決）。純 GET 表單，按 Enter 或沒有 JS 時都送出
                    交給後端；換關鍵字等於重新送出這個表單、網址沒帶 page，會自動
                    落回第 1 頁，跟品牌／標籤／排序共用同一套機制（見上面
                    partials.tag-filter 的 otherParams）。分頁與 tags[] 一起帶著走
                    靠 repository 的 withQueryString()，不用在這裡另外處理。
                --}}
                <form method="GET" action="{{ $currentUrl }}" class="uq-search-form">
                    @foreach ($selectedTagValues as $tagValue)
                        <input type="hidden" name="tags[]" value="{{ $tagValue }}">
                    @endforeach

                    <i class="search icon" aria-hidden="true"></i>
                    <input type="search" name="q" class="uq-control uq-search-input" value="{{ $currentQ }}"
                        placeholder="在這個分類裡找…" maxlength="50" aria-label="在這個分類裡找">
                    @if ($currentQ !== '')
                        <a class="uq-search-clear" href="{{ $currentUrl }}?{{ $queryFor(['q' => null]) }}"
                            aria-label="清除搜尋">&times;</a>
                    @endif
                </form>
            </x-slot:start>

            <x-slot:end>
                @include('partials.tag-filter-button')
            </x-slot:end>
        </x-toolbar>

        @include('partials.tag-filter')

        {{-- 篩到 0 件時整個卡片容器與分頁都不該出現，只留一句話說明現在的狀況 --}}
        @if ($hmallProducts->isEmpty())
            @include('partials.empty-state', [
                'icon' => 'search faded',
                'title' => $currentQ !== '' ? "沒有符合「{$currentQ}」的商品" : '沒有符合的商品',
                'hint' => $currentQ !== '' ? '試試看換個關鍵字，或少選幾個條件' : '試試看少選幾個條件',
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

@section('javascript')
    <script src="{{ asset('js/list-search.js') }}?v={{ filemtime(public_path('js/list-search.js')) }}"></script>
@endsection
