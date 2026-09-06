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
    {{-- 段與段之間的距離由 .uq-h2 的上緣間距負責，不再靠一層 tab segment 加幾條隱形分隔線 --}}
    @foreach ($genders as $key => $label)
        @include('partials.section-anchor', ['anchor' => $key, 'menu' => 'gender_menu'])
        {{--
            data-gender-heading／data-gender-body 給 list-search.js 用：邊打邊篩把一個
            性別段的卡片全部藏起來時，連這段標題跟章節選單的項目也要跟著藏。故意不多包
            一層容器 div：.uq-section-anchor 必須是 .ts.container 的直接子節點、後面
            緊接著 .uq-h2，app.css 靠這個結構把選單後第一段的上緣間距歸零，多包一層會
            把那個選擇器斷開。
        --}}
        <h2 class="unstyled uq-h2" data-gender-heading="{{ $key }}">
            {{ $label }}
            <span class="uq-count">{{ count($hmallProductList[$key]) }} 件</span>
        </h2>
        @if (count($hmallProductList[$key]) > 0)
            <div class="ts doubling link cards four" data-gender-body="{{ $key }}">
                @foreach ($hmallProductList[$key] as $hmallProduct)
                    @include('hmall-products.card', ['hmallProduct' => $hmallProduct])
                @endforeach
            </div>
        @else
            {{-- master 在這裡就是一句「沒有商品」：這一段有沒有貨也是資訊，不受搜尋影響 --}}
            <p class="uq-none">沒有商品</p>
        @endif
    @endforeach
@endif
