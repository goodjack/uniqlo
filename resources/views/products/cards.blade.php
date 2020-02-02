<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        <div id="category" class="ts four item large pointing secondary menu">
            <a class="active item" data-tab="Men">MEN</a>
            <a class="item" data-tab="Women">WOMEN</a>
            <a class="item" data-tab="Kids">KIDS</a>
            <a class="item" data-tab="Baby">BABY</a>
        </div>
        <div data-tab="Men" class="ts active basic horizontally fitted tab segment">
            <h2 class="ts large header">
                男裝
                <div class="inline sub header">共 {{ count($products['men']) }} 件</div>
            </h2>
            @if (count($products['men']) > 0)
            <div class="ts doubling link cards four">
                @each('products.card', $products['men'], 'product')
            </div>
            @else
                @include('products.card-empty')
            @endif
        </div>
        <div data-tab="Women" class="ts basic horizontally fitted tab segment">
            <h2 class="ts large header">
                女裝
                <div class="inline sub header">共 {{ count($products['women']) }} 件</div>
            </h2>
            @if (count($products['women']) > 0)
            <div class="ts doubling link cards four">
                @each('products.card', $products['women'], 'product')
            </div>
            @else
                @include('products.card-empty')
            @endif
        </div>
        <div data-tab="Kids" class="ts basic horizontally fitted tab segment">
            <h2 class="ts large header">
                童裝
                <div class="inline sub header">共 {{ count($products['kids']) }} 件</div>
            </h2>
            @if (count($products['kids']) > 0)
            <div class="ts doubling link cards four">
                @each('products.card', $products['kids'], 'product')
            </div>
            @else
                @include('products.card-empty')
            @endif
        </div>
        <div data-tab="Baby" class="ts basic horizontally fitted tab segment">
            <h2 class="ts large header">
                嬰幼兒
                <div class="inline sub header">共 {{ count($products['baby']) }} 件</div>
            </h2>
            @if (count($products['baby']) > 0)
            <div class="ts doubling link cards four">
                @each('products.card', $products['baby'], 'product')
            </div>
            @else
                @include('products.card-empty')
            @endif
        </div>
    </div>
</div>

@section('javascript')
    @parent
    <script>
        ts('#category .item').tab();
    </script>
@endsection
