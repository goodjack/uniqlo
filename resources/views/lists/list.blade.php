@extends('layouts.master')

@php
    $currentUrl = url()->current();
    // <title> 與社群描述照 master 的句型（「65 件商品期間限定特價中」），
    // 頁面上的標題維持新版的「期間限定特價」加副標
    $title = "{$count} 件{$titleName}";

    // 品牌、排序、標籤與搜尋關鍵字是四個獨立的軸，切換其中一個要保留另外三個
    $queryFor = fn(array $changes) => http_build_query(
        array_filter(array_merge(request()->only(['brand', 'sort', 'tags', 'q']), $changes))
    );

    $currentBrand = in_array(request('brand'), ['UNIQLO', 'GU'], true) ? request('brand') : null;
    $selectedTagValues = collect(\App\Enums\ProductTag::fromValues((array) request('tags', [])))
        ->map->value
        ->all();
    // ListRequest::prepareForValidation() 已經修剪並截斷過，這裡直接讀就是乾淨的值
    $currentQ = (string) request('q');

    // 副標講的是實際排序，不是預設排序：使用者選了價格排序之後還寫預設就是錯的
    $sortText = request('sort') === \App\Services\ListService::SORT_PRICE_ASC ? '依價格由低到高排序' : $sortSummary;

    /*
     * 四個性別段一律都出現，沒有商品的那段寫「沒有商品」——「男裝 0 件」本身
     * 就是資訊，master 也是四段都列。整頁一件都沒有（通常是標籤篩太窄）才換成
     * 空狀態，那是另一回事。
     *
     * 章節選單跟正文共用同一份清單，抬到這裡算一次就好；也才能讓選單站在
     * container 外面、正文站在另一個 container 裡面（見下面 section-menu 的說明）。
     */
    $genders = collect(['men' => '男裝', 'women' => '女裝', 'kids' => '童裝', 'baby' => '嬰幼兒']);
@endphp

@section('title', $title)

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    @if ($currentQ !== '')
        {{-- 帶關鍵字的清單頁是既有清單的重組，不需要另外被索引，做法照搜尋結果頁 --}}
        <meta name="robots" content="noindex, follow" />
    @endif
    <meta name="description" content="{{ $title }} | UNIQLO 比價 | UQ 搜尋" />
    <meta property="og:title" content="{{ $typeName }} | UQ 搜尋" />
    <meta property="og:url" content="{{ $currentUrl }}" />
    <meta property="og:description" content="{{ $title }} | UNIQLO 比價 | UQ 搜尋" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:creator" content="@littlegoodjack" />
    <meta name="twitter:title" content="{{ $typeName }} | UQ 搜尋" />
    <meta name="twitter:description" content="{{ $title }} | UNIQLO 比價 | UQ 搜尋" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="{{ $typeStyle }} {{ $typeIcon }} icon"></i>
        <span class="header">{{ $typeName }}</span>
        <span class="description">
            @if ($currentQ !== '')
                {{ $count }} 件符合「{{ $currentQ }}」，{{ $sortText }}
            @else
                {{ $count }} 件，{{ $sortText }}
            @endif
        </span>
    </div>

    <div class="ts container uq-page">
        <x-toolbar>
            <x-slot:start>
                {{-- 三顆分開的 pill 不是連在一起的按鈕群：連著的那組會被讀成一顆分段控制項， --}}
                {{-- 但這裡每一顆都是各自的網址、按了是換頁 --}}
                <a class="uq-control uq-pill {{ $currentBrand === null ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['brand' => null]) }}">全部</a>
                <a class="uq-control uq-pill {{ $currentBrand === 'UNIQLO' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'UNIQLO']) }}">UNIQLO</a>
                <a class="uq-control uq-pill {{ $currentBrand === 'GU' ? 'active' : '' }}"
                    href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'GU']) }}">GU</a>

                {{--
                    在這個清單裡找。GET 表單搭配前端即時篩（list-search.js）：
                    有 JS 時邊打邊篩畫面上的卡片，按 Enter 或沒有 JS 時照樣送出，
                    伺服器對這個清單預熱好的 Collection 篩品名與編號
                    （ListService::filterHmallProducts()）。跟品牌、排序、標籤一樣
                    是獨立的軸，所以要帶上其他三個的隱藏欄位，其他三個的連結／
                    表單也要把 q 帶著（見上面 $queryFor 與下面 uq-sort-form）。
                --}}
                <form method="GET" action="{{ $currentUrl }}" class="uq-search-form">
                    @if ($currentBrand)
                        <input type="hidden" name="brand" value="{{ $currentBrand }}">
                    @endif
                    @if (request('sort') === \App\Services\ListService::SORT_PRICE_ASC)
                        <input type="hidden" name="sort" value="{{ \App\Services\ListService::SORT_PRICE_ASC }}">
                    @endif
                    @foreach ($selectedTagValues as $tagValue)
                        <input type="hidden" name="tags[]" value="{{ $tagValue }}">
                    @endforeach

                    <i class="search icon" aria-hidden="true"></i>
                    <input type="search" name="q" class="uq-control uq-search-input" value="{{ $currentQ }}"
                        placeholder="在這個清單裡找…" maxlength="50" aria-label="在這個清單裡找">
                    @if ($currentQ !== '')
                        <a class="uq-search-clear" href="{{ $currentUrl }}?{{ $queryFor(['q' => null]) }}"
                            aria-label="清除搜尋">&times;</a>
                    @endif
                </form>
            </x-slot:start>

            <x-slot:end>
                {{-- 原生 <select>，不吃 JavaScript。不掛 Tocas 的 dropdown：那組規則自帶 --}}
                {{-- 高度、圓角與箭頭，蓋起來比從 .uq-control 長還費事 --}}
                <form method="GET" action="{{ $currentUrl }}" class="uq-sort-form">
                    @if ($currentBrand)
                        <input type="hidden" name="brand" value="{{ $currentBrand }}">
                    @endif
                    @foreach ($selectedTagValues as $tagValue)
                        <input type="hidden" name="tags[]" value="{{ $tagValue }}">
                    @endforeach
                    @if ($currentQ !== '')
                        <input type="hidden" name="q" value="{{ $currentQ }}">
                    @endif

                    <select class="uq-control uq-sort" name="sort" data-auto-submit aria-label="排序方式">
                        <option value="" @selected(request('sort') !== \App\Services\ListService::SORT_PRICE_ASC)>排序：預設</option>
                        <option value="{{ \App\Services\ListService::SORT_PRICE_ASC }}" @selected(request('sort') === \App\Services\ListService::SORT_PRICE_ASC)>排序：價格由低到高</option>
                    </select>
                    {{-- 沒有 JavaScript 時這顆是唯一的出路，有的時候 master 版型會把它藏起來 --}}
                    <button class="uq-control" type="submit" data-auto-submit-fallback>套用</button>
                </form>

                @include('partials.tag-filter-button')
            </x-slot:end>
        </x-toolbar>

        @include('partials.tag-filter')
    </div>

    @if ($count > 0)
        {{-- 性別是這一頁的章節，選單用跟商品頁、分類總覽同一個 partial。它自己 --}}
        {{-- 站在上面那個 container 外面，白底跟底線才是滿版、不是只跨中間那欄 --}}
        @include('partials.section-menu', [
            'id' => 'gender_menu',
            'items' => $genders
                ->map(fn ($label, $key) => [
                    'anchor' => $key,
                    'label' => $label,
                    'count' => count($hmallProductList[$key]),
                ])
                ->values()
                ->all(),
        ])
    @endif

    <div class="ts container">
        @include('lists.cards', [
            'hmallProductList' => $hmallProductList,
            'genders' => $genders,
            'count' => $count,
        ])
    </div>
@endsection

@section('javascript')
    <script src="{{ asset('js/list-search.js') }}?v={{ filemtime(public_path('js/list-search.js')) }}"></script>
@endsection
