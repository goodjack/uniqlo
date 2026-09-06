@php
    // 原價只在有資料且大於現價時顯示——origin_price 是爬蟲寫進去的原始欄位，
    // 沒打折的商品這欄常常跟現價相等甚至是 null，那種不算「有優惠」
    $hasOriginPrice = $hmallProduct->origin_price !== null
        && (float) $hmallProduct->origin_price > $hmallProduct->price;
@endphp
{{--
    現價是卡片上第二重要的資訊（第一是圖）。跟官網一樣：有優惠時原價一行
    刪除線在上、現價一行加粗在下；沒有原價可刪就只剩現價一行，優惠色跟著
    拿掉（見 app.css 的 .uq-card-now:only-child）。舊版在這裡放的是歷史
    高低價區間，跟官網的資訊層級對不起來，v3 拿掉、換成原價／現價。
--}}
<div class="uq-card-price">
    @if ($hasOriginPrice)
        <del class="uq-card-origin">原價 ${{ (int) $hmallProduct->origin_price }}</del>
    @endif
    <span class="uq-card-now">${{ $hmallProduct->price }}</span>
</div>
@include('hmall-products.partials.card-labels')
