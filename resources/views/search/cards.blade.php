<h2 class="ts large header">
    同編號商品
    <div class="inline sub header">共 {{ $hmallProducts->count() }} 件</div>
</h2>
<div class="ts doubling cards four uq-product-cards">
    {{--
        UNIQLO 常把多個貨號放在同一個商品頁，code 不等於查詢字的卡片要補一行
        「同時包含貨號」。提示塞進卡片的 slot：.ts.cards 的欄寬算的是直接子元素。
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
            'price' => view('hmall-products.partials.card-price', ['hmallProduct' => $hmallProduct]),
            'slot' => view('hmall-products.partials.card-labels', ['hmallProduct' => $hmallProduct])->render()
                .(empty($sharedCodes) ? '' : '<div class="meta">此商品頁同時包含貨號 '.e(implode(' / ', $sharedCodes)).'</div>'),
        ])
    @endforeach
</div>

@if ($products->isNotEmpty())
    <h2 class="ts large header">
        舊系統商品
        <div class="inline sub header">共 {{ $products->count() }} 件</div>
    </h2>
    <div class="ts doubling cards four uq-product-cards">
        @each('products.card', $products, 'product')
    </div>
@endif
