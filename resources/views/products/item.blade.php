@inject('productPresenter', 'App\Presenters\ProductPresenter')

<a class="item" href="{{ $productPresenter->getProductUrl($product) }}">
    <i class="large image icon"></i>
    <div class="middle aligned content">
        <div class="header">
            {{ $product->name }}
        </div>
        <div class="middoted meta">
            <span>舊系統</span>
            <span>{{ $product->id }}</span>
            {!! $productPresenter->getRatingForProductCardAndItem($product) !!}
        </div>
    </div>
</a>
