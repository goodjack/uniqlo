@inject('productPresenter', 'App\Presenters\ProductPresenter')

<a class="ts flatted card" href="{{ $productPresenter->getProductUrl($product) }}">
    <div class="image">
        <img data-src="{{ $product->main_image_url }}" class="lazyload" loading="lazy" alt="{{ $product->name }} {{ $product->id }}">
    </div>
    <div class="content">
        <div class="smaller header">{{ $product->name }}</div>
        <div class="meta">{{ $product->id }}</div>
    </div>
    <div class="center aligned extra content">
        @if ($product->max_price > $product->price)
        <small>${{ $product->max_price }} ⇢</small>
        <strong>${{ $product->price }}</strong>
        @else
        ${{ $product->price }}
        @endif

        @if ($product->price > $product->min_price)
        <span style="color: #8BB96E;">⇢ ${{ $product->min_price }}</span>
        @endif

        @if ($product->limit_sales_end_msg)
        <br><span style="color: #CE5F58;">{{ $product->limit_sales_end_msg }}</span>
        @endif

        @if ($product->multi_buy)
        <br><span style="color: #CE5F58;">{{ $product->multi_buy }}</span>
        @endif

        @if ($product->sale)
        <br><span style="color: #79A8B9;">特價商品</span>
        @endif

        @if ($product->new)
        <br><span style="color: #79A8B9;">新品</span>
        @endif
    </div>
    @if ($product->price > $product->min_price)
    <div class="symbol">
        <i class="caution circle icon"></i>
    </div>
    @endif
</a>
