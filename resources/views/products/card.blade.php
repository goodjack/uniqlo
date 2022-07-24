@inject('productPresenter', 'App\Presenters\ProductPresenter')

<a class="ts flatted card" href="{{ $productPresenter->getProductUrl($product) }}">
    <div class="content">
        <div class="smaller header">{{ $product->name }}</div>
        <div class="middoted meta">
            <span>{{ $product->id }}</span>
            {!! $productPresenter->getRatingForProductCardAndItem($product) !!}
        </div>
    </div>
    <div class="center aligned extra content">
        <div class="description">
            <p>
                ${{ $product->max_price }} - ${{ $product->min_price }}
            </p>
            <div class="ts horizontal basic circular label">舊系統</div>
        </div>

        {{-- @if ($product->limit_sales_end_msg)
            <br><span style="color: #CE5F58;">{{ $product->limit_sales_end_msg }}</span>
        @endif

        @if ($product->multi_buy)
            <br><span style="color: #79A8B9;">{{ $product->multi_buy }}</span>
        @endif

        @if ($product->sale)
            <br><span style="color: #00ADEA;">特價商品</span>
        @endif

        @if ($product->new)
            <br><span style="color: #8BB96E;">新品</span>
        @endif --}}
    </div>
    {{-- @if ($product->price > $product->min_price)
        <div class="symbol">
            <i class="caution circle icon"></i>
        </div>
    @endif --}}
</a>
