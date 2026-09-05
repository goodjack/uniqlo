{{-- 現價是卡片上第二重要的資訊（第一是圖），歷史區間是它的註腳，所以縮成灰字 --}}
<div class="uq-card-price">
    ${{ $hmallProduct->price }}
    @if ($hmallProduct->highest_record_price !== $hmallProduct->lowest_record_price)
        <span class="uq-card-price-range">
            ${{ (int) $hmallProduct->highest_record_price }}
            -
            @if ($hmallProduct->price > $hmallProduct->lowest_record_price)
                <span class="uq-card-price-low">${{ (int) $hmallProduct->lowest_record_price }}</span>
            @else
                ${{ (int) $hmallProduct->lowest_record_price }}
            @endif
        </span>
    @endif
</div>
@include('hmall-products.partials.card-labels')
