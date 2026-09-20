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
        {{--
            data-instant-subtitle／data-sort-text 給 list-search.js 用：即時篩
            之後這句話的件數跟符合關鍵字要重算，sortText 是固定不變的那一段
            （即時篩不影響排序），先存成 data 屬性讓腳本組字串時不用重新推算。
            這裡刻意不在文字節點裡插標籤——ListSearchTest 的
            assertStringContainsString('N 件符合「關鍵字」') 是比對原始 HTML
            字串，插進去的標籤會插斷這段文字，讓字串比對失敗。
        --}}
        <span class="description" data-instant-subtitle data-sort-text="{{ $sortText }}">
            @if ($currentQ !== '')
                {{ $count }} 件符合「{{ $currentQ }}」，{{ $sortText }}
            @else
                {{ $count }} 件，{{ $sortText }}
            @endif
        </span>
    </div>

    <div class="ts container">
        <x-toolbar>
            <x-slot:start>
                {{--
                    品牌切換回 master 的 Tocas 按鈕群，選中的那顆掛 Tocas 的 .active。

                    不加 basic：Tocas 自己的 .ts.basic.buttons .button.active 是 #414141 底
                    配 #272727 字，實測對比 1.3:1，選中哪一顆看不出來。實心版的 .active 是
                    #bfbfbf 底配 #404040 字，正常、hover、選中三態 Tocas 成套處理，零覆寫。
                    尺寸用 Tocas 的 compact（35.47px、字仍是 14px）。這一列現在是品牌、
                    搜尋、排序、篩選四種控制項排在一起，不是 master 那個獨立的品牌切換，
                    所以尺寸要照「同一列的工具列操作」來挑：small（38.19px）只少 3px，
                    收不出效果；tiny（35.42px）跟 compact 幾乎同高卻把字降到 12px，這是
                    高頻操作、不該犧牲可讀性；master 那個 small very compact（30.03px）
                    會跟底下的圓角條件同高，看不出「工具列操作」與「已選條件」是兩件事，
                    手機上也偏擠。同一列的搜尋框與排序下拉 Tocas 沒有現成的同高版本，
                    由 app.css 補到同一個高度——只縮按鈕會讓同一列又變成兩種高度。
                --}}
                <div class="ts compact buttons">
                    <a class="ts button {{ $currentBrand === null ? 'active' : '' }}"
                        href="{{ $currentUrl }}?{{ $queryFor(['brand' => null]) }}">全部</a>
                    <a class="ts button {{ $currentBrand === 'UNIQLO' ? 'active' : '' }}"
                        href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'UNIQLO']) }}">UNIQLO</a>
                    <a class="ts button {{ $currentBrand === 'GU' ? 'active' : '' }}"
                        href="{{ $currentUrl }}?{{ $queryFor(['brand' => 'GU']) }}">GU</a>
                </div>
            </x-slot:start>

            <x-slot:end>
                {{--
                    在這個清單裡找。GET 表單搭配前端即時篩（list-search.js）：
                    有 JS 時邊打邊篩畫面上的卡片，按 Enter 或沒有 JS 時照樣送出，
                    伺服器對這個清單預熱好的 Collection 篩品名與編號
                    （ListService::filterHmallProducts()）。跟品牌、排序、標籤一樣
                    是獨立的軸，所以要帶上其他三個的隱藏欄位，其他三個的連結／
                    表單也要把 q 帶著（見上面 $queryFor 與下面 uq-sort-form）。
                --}}
                <form method="GET" action="{{ $currentUrl }}" class="ts input uq-search-form">
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
                    <input type="search" name="q" class="uq-search-input" data-instant-filter
                        value="{{ $currentQ }}" placeholder="在這個清單裡找…" maxlength="50" aria-label="在這個清單裡找">
                    @if ($currentQ !== '')
                        <a class="uq-search-clear" href="{{ $currentUrl }}?{{ $queryFor(['q' => null]) }}"
                            aria-label="清除搜尋">&times;</a>
                    @endif
                </form>

                {{-- 原生 <select>，不吃 JavaScript。外觀用 Tocas 的 .ts.basic.dropdown， --}}
                {{-- 它本來就是給 <select> 用的，連下拉箭頭都畫好了 --}}
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

                    <select class="ts basic dropdown" name="sort" data-auto-submit aria-label="排序方式">
                        <option value="" @selected(request('sort') !== \App\Services\ListService::SORT_PRICE_ASC)>排序：預設</option>
                        <option value="{{ \App\Services\ListService::SORT_PRICE_ASC }}" @selected(request('sort') === \App\Services\ListService::SORT_PRICE_ASC)>排序：價格由低到高</option>
                    </select>
                    {{-- 沒有 JavaScript 時這顆是唯一的出路，有的時候 master 版型會把它藏起來 --}}
                    <button class="ts basic compact button" type="submit" data-auto-submit-fallback>套用</button>
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

    {{--
        章節選單自己不留下方 margin（見 app.css 的 .uq-section-menu），選單到
        第一段標題的距離改由這一層 Tocas segment 負責：basic 去掉框線與底色、
        horizontally fitted 去掉左右 padding 交給裡面的 container，剩下的是上下
        各 1em padding 加 1rem margin。實測選單底到「男裝」28px，跟 master 同一
        個位置量出來的一樣（master 用的是 ts active basic horizontally fitted
        tab segment，這裡不需要 tab 的語意）。

        包在 container 外面不是裡面：Tocas 有 .ts.segment:first-child{margin-top:0}，
        擺進 container 裡它就是第一個子元素、margin 被歸零，只剩 14px。
    --}}
    <div class="ts basic horizontally fitted segment">
        <div class="ts container">
            @include('lists.cards', [
                'hmallProductList' => $hmallProductList,
                'genders' => $genders,
                'count' => $count,
            ])
        </div>
    </div>
@endsection

@section('javascript')
    <script src="{{ asset('js/list-search.js') }}?v={{ filemtime(public_path('js/list-search.js')) }}"></script>
@endsection
