<h2 class="unstyled uq-h2">
    同編號商品
    <span class="uq-count">{{ $hmallProducts->count() }} 件</span>
</h2>
<div class="ts doubling link cards four">
    @each('hmall-products.card', $hmallProducts, 'hmallProduct')
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
