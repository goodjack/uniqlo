@inject('productPresenter', 'App\Presenters\ProductPresenter')

<a class="item" href="{{ $productPresenter->getProductUrl($product) }}">
    <div class="ts tiny image">
        <img src="{{ $product->main_image_url }}">
    </div>
    <div class="middle aligned content">
        <div class="header">
            {{ $product->name }}
        </div>
        <div class="meta">
            <span>{{ $product->id }}</span>
        </div>
    </div>
</a>
