<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts narrow container">
        <div id="category" class="ts top attached four item tabbed menu">
            <a class="active item" data-tab="Men">男裝 ({{ count($products['men']) }})</a>
            <a class="item" data-tab="Women">女裝 ({{ count($products['women']) }})</a>
            <a class="item" data-tab="Kids">童裝 ({{ count($products['kids']) }})</a>
            <a class="item" data-tab="Baby">嬰幼兒 ({{ count($products['baby']) }})</a>
        </div>
        <div data-tab="Men" class="ts active bottom attached tab segment">
            <div class="ts doubling link cards four">
                @each('products.card', $products['men'], 'product')
            </div>
        </div>
        <div data-tab="Women" class="ts bottom attached tab segment">
            <div class="ts doubling link cards four">
                @each('products.card', $products['women'], 'product')
            </div>
        </div>
        <div data-tab="Kids" class="ts bottom attached tab segment">
            <div class="ts doubling link cards four">
                @each('products.card', $products['kids'], 'product')
            </div>
        </div>
        <div data-tab="Baby" class="ts bottom attached tab segment">
            <div class="ts doubling link cards four">
                @each('products.card', $products['baby'], 'product')
            </div>
        </div>
    </div>
</div>

@section('javascript')
    @parent
    <script>
        ts('#category.tabbed.menu .item').tab();
    </script>
@endsection