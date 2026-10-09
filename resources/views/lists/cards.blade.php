{{--
    genders  四個性別，跟章節選單共用 lists/list.blade.php 算好的同一份
    count    篩選後的總件數；0 件時整頁換成空狀態，不列四段「沒有商品」
--}}
@php
    $currentQ = (string) request('q');
@endphp

@if ($count === 0)
    @include('partials.empty-state', [
        'icon' => 'search faded',
        'title' => $currentQ !== '' ? "沒有符合「{$currentQ}」的商品" : '沒有符合的商品',
        'hint' => $currentQ !== '' ? '試試看換個關鍵字，或少選幾個條件' : '試試看少選幾個條件',
    ])
@else
    {{-- list-search.js 即時篩到一張都不剩時才顯示，同時藏起下面四段與章節選單 --}}
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
        {{-- data-gender-* 給 list-search.js：一段篩光時連標題一起藏，件數要重算 --}}
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
            <p>沒有商品</p>
        @endif

        @if (!$loop->last)
            <div class="ts hidden section divider"></div>
        @endif
    @endforeach

    <div class="ts hidden divider"></div>
@endif
