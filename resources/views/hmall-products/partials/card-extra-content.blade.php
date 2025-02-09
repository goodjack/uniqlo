@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')
<div class="ts hidden divider"></div>
<div class="header">
    ${{ $hmallProduct->price }}
    @if ($hmallProduct->highest_record_price !== $hmallProduct->lowest_record_price)
        <div class="sub header">
            ${{ (int) $hmallProduct->highest_record_price }}
            -
            @if ($hmallProduct->price > $hmallProduct->lowest_record_price)
                <span style="color: #8BB96E;">
                    ${{ (int) $hmallProduct->lowest_record_price }}
                </span>
            @else
                ${{ (int) $hmallProduct->lowest_record_price }}
            @endif
        </div>
    @endif
</div>
@include('hmall-products.partials.card-labels')
