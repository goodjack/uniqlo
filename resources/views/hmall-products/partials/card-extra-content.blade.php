{{-- 不放原價：歷史區間已經回答「現在算不算便宜」，官方原價又常等於歷史最高 --}}
<div class="ts hidden divider"></div>
<div class="header">
    ${{ $hmallProduct->price }}
    @if ($hmallProduct->highest_record_price !== $hmallProduct->lowest_record_price)
        <div class="sub header">
            ${{ (int) $hmallProduct->highest_record_price }}
            -
            {{-- 現價還高於歷史最低，代表還可以再等，最低價染綠 --}}
            @if ($hmallProduct->price > $hmallProduct->lowest_record_price)
                <span style="color: var(--uq-new-text);">
                    ${{ (int) $hmallProduct->lowest_record_price }}
                </span>
            @else
                ${{ (int) $hmallProduct->lowest_record_price }}
            @endif
        </div>
    @endif
</div>
@include('hmall-products.partials.card-labels')
