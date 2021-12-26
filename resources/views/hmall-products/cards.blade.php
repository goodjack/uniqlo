<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        <div data-tab="Men" class="ts active basic horizontally fitted tab segment">
            <h2 class="ts large header">
                同編號商品
                <div class="inline sub header">共 {{ $hmallProducts->count() }} 件</div>
            </h2>
            <div class="ts doubling link cards four">
                @each('hmall-products.card', $hmallProducts, 'hmallProduct')
            </div>
            @if ($products->isNotEmpty())
                <h2 class="ts large header">
                    舊系統商品
                    <div class="inline sub header">共 {{ $products->count() }} 件</div>
                </h2>
                <div class="ts doubling link cards four">
                    @each('products.card', $products, 'product')
                </div>
            @endif
        </div>
    </div>
</div>
