<h2 class="unstyled uq-h2">
    同編號商品
    <span class="uq-count">{{ $hmallProducts->count() }} 件</span>
</h2>
<div class="ts doubling link cards four">
    {{--
        不能用 @each：UNIQLO 常把多個貨號共用同一個商品頁，這裡的 code 只有部分
        跟查詢字一樣，其餘是 name 裡帶出來的號碼（見 HmallProductRepository::
        findHmallProductsByCodeOrSharedNumber）。要逐張卡片判斷、補一行「此商品頁
        同時包含貨號」的提示，@each 沒辦法帶額外變數進子視圖。

        提示塞進卡片既有的 slot（card-extra-content 之後），不額外加卡片的兄弟
        節點——.ts.four.cards>.ts.card 的欄寬是照直接子元素數的，多塞非 .card
        的節點會把版面擠壞。
    --}}
    @foreach ($hmallProducts as $hmallProduct)
        @php
            $sharedCodes = [];
            if ($hmallProduct->code !== $query) {
                preg_match_all('/\d{6}/', $hmallProduct->name, $sharedCodeMatches);
                $sharedCodes = $sharedCodeMatches[0];
            }
        @endphp
        @include('hmall-products.partials.card-base', [
            'slot' => view('hmall-products.partials.card-extra-content', ['hmallProduct' => $hmallProduct])->render()
                .(empty($sharedCodes) ? '' : '<p class="uq-shared-codes">此商品頁同時包含貨號 '.e(implode(' / ', $sharedCodes)).'</p>'),
        ])
    @endforeach
</div>

@if ($products->isNotEmpty())
    <h2 class="unstyled uq-h2">
        舊系統商品
        <span class="uq-count">{{ $products->count() }} 件</span>
    </h2>
    <div class="ts doubling link cards four">
        @each('products.card', $products, 'product')
    </div>
@endif
