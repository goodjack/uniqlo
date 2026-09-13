{{--
    價格回到 master 的兩行：現價（.header）加歷史區間（.sub.header）。

    原價刪除線拿掉了。這個站回答的是「這個價位在它自己的歷史裡算不算便宜」，
    區間那一行已經把歷史最高與最低都講完；再放一條「原價 $XXX」是用另一個
    口徑講同一件事，而官方的 origin_price 又常常就等於歷史最高，兩行讀起來
    重複。有沒有在打折改看底下的狀態標籤（特價商品、歷史新低價）。
--}}
<div class="ts hidden divider"></div>
<div class="header">
    ${{ $hmallProduct->price }}
    {{-- 歷史高低一樣就沒有區間可講（只記錄過一次價格的商品就是這樣） --}}
    @if ($hmallProduct->highest_record_price !== $hmallProduct->lowest_record_price)
        <div class="sub header">
            ${{ (int) $hmallProduct->highest_record_price }}
            -
            {{--
                現價還高於歷史最低，代表「還可以再等」，那個最低價染綠。這裡跟
                HmallProductPresenter::COLOR_NEW 是同一個顏色（同樣讀
                --uq-new-text），2026-09 這輪一起加深：原本寫死的 #8BB96E 在白底
                只有 2.27:1，不到 4.5:1。
            --}}
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
