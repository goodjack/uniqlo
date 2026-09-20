{{--
    清單頁的正文：依性別分段的卡片 grid，或者一句話的空狀態。

    genders  ['men' => '男裝', ...] 四個性別的完整清單，在 lists/list.blade.php
             算好才傳進來——章節選單跟這裡共用同一份，兩邊不能各自濾一次然後
             濾出不同的段落。
    count    篩選後的總件數。一件都沒有時整頁換成空狀態，不是列四段「沒有商品」。
--}}
@php
    // 有搜尋關鍵字時空狀態的文案要點名關鍵字，跟「篩標籤篩到沒有」是不同情境
    $currentQ = (string) request('q');
@endphp

@if ($count === 0)
    @include('partials.empty-state', [
        'icon' => 'search faded',
        'title' => $currentQ !== '' ? "沒有符合「{$currentQ}」的商品" : '沒有符合的商品',
        'hint' => $currentQ !== '' ? '試試看換個關鍵字，或少選幾個條件' : '試試看少選幾個條件',
    ])
@else
    {{--
        即時篩把畫面上的卡片全部篩光時要換的空狀態，跟上面 $count === 0 那個
        是同一種情境（篩到 0 筆），只是這裡是前端即時篩篩到 0 筆、伺服器端的
        $count 其實還大於 0。平常藏著，list-search.js 篩到一張都不剩時才取消
        隱藏，同時把下面四段（含固定顯示的「沒有商品」）跟章節選單一起藏起來
        ——那是「四段都在、只是某段沒貨」的版面，跟「搜尋沒有結果」的空狀態
        是兩種不同的意思，不能同時顯示。
    --}}
    <div class="ts center aligned basic segment" id="list-instant-empty-state" hidden>
        <div class="ts icon header">
            <i class="search faded icon"></i>
            <div class="content">
                <span id="list-instant-empty-title"></span>
                <div class="sub header">試試看換個關鍵字，或少選幾個條件</div>
            </div>
        </div>
    </div>

    @foreach ($genders as $key => $label)
        @include('partials.section-anchor', ['anchor' => $key, 'menu' => 'gender_menu'])
        {{--
            data-gender-heading／data-gender-body 給 list-search.js 用：邊打邊篩把一個
            性別段的卡片全部藏起來時，連這段標題跟章節選單的項目也要跟著藏（藏起來靠
            原生的 hidden 屬性，Tocas 自己就有 [hidden]{display:none !important}，
            不用另外補規則）。data-gender-count 包住的數字是即時篩之後要重算、
            重寫文字內容的部分，外面的「共」「件」文字不動——ListSearchTest 用
            DOMDocument 讀 textContent，標籤不影響比對結果。
        --}}
        <h2 class="ts large header" data-gender-heading="{{ $key }}">
            {{ $label }}
            <div class="inline sub header">共 <span data-gender-count>{{ count($hmallProductList[$key]) }}</span> 件</div>
        </h2>
        @if (count($hmallProductList[$key]) > 0)
            <div class="ts doubling cards four uq-product-cards" data-gender-body="{{ $key }}">
                @foreach ($hmallProductList[$key] as $hmallProduct)
                    @include('hmall-products.card', ['hmallProduct' => $hmallProduct])
                @endforeach
            </div>
        @else
            {{-- master 在這裡就是一句「沒有商品」：這一段有沒有貨也是資訊，不受搜尋影響 --}}
            <p>沒有商品</p>
        @endif

        {{--
            段與段之間的分隔，照 master 原樣。它跟標題自己的上距是兩件事：上距是
            標題在自己那一段裡的呼吸空間，這一條說的是上一群商品到此為止、下一群
            開始。最後一段不加，那裡已經有頁尾前的分隔。
        --}}
        @if (!$loop->last)
            <div class="ts hidden section divider"></div>
        @endif
    @endforeach

    <div class="ts hidden divider"></div>
@endif
