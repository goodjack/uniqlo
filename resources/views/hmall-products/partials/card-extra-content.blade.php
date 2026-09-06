@php
    // 原價只在有資料且大於現價時顯示——origin_price 是爬蟲寫進去的原始欄位，
    // 沒打折的商品這欄常常跟現價相等甚至是 null，那種不算「有優惠」
    $hasOriginPrice = $hmallProduct->origin_price !== null
        && (float) $hmallProduct->origin_price > $hmallProduct->price;

    // 歷史高低一樣就沒有區間可講（只記錄過一次價格的商品就是這樣）
    $hasPriceRange = $hmallProduct->highest_record_price !== $hmallProduct->lowest_record_price;

    // 現價還高於歷史最低，代表「還可以再等」，master 用綠字標那個最低價
    $isAboveLowestRecord = $hmallProduct->price > $hmallProduct->lowest_record_price;
@endphp
{{-- master 用一條隱形分隔線把品名與價格分開，比 8px 的 margin 多留一點空間 --}}
<div class="ts hidden divider"></div>
{{--
    比價站的卡片要回答兩件事：現在多少錢、這個價位在它自己的歷史裡算便宜嗎。
    所以第一行是歷史區間（$歷史最高 - $歷史最低），第二行是原價刪除線加現價。
    區間那一行是 master 的做法，v3 拿掉過，但那是這個站存在的理由，還原回來。
--}}
<div class="uq-card-price">
    @if ($hasPriceRange)
        <div class="uq-card-range">
            ${{ (int) $hmallProduct->highest_record_price }}
            -
            <span @if ($isAboveLowestRecord) style="color: #8BB96E;" @endif>${{ (int) $hmallProduct->lowest_record_price }}</span>
        </div>
    @endif
    <div class="uq-card-now-row">
        @if ($hasOriginPrice)
            <del class="uq-card-origin">原價 ${{ (int) $hmallProduct->origin_price }}</del>
        @endif
        <span class="uq-card-now">${{ $hmallProduct->price }}</span>
    </div>
</div>
@include('hmall-products.partials.card-labels')
