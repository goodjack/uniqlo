@inject('productPresenter', 'App\Presenters\ProductPresenter')

<div class="ts doubling link cards four">
    @foreach ($products as $product)
    <a class="ts negative card" href="{{ $productPresenter->getProductUrl($product) }}">
        <div class="image">
            <img src="{{ $product->main_image_url }}">
        </div>
        <div class="content">
            <div class="header">{{ $product->name }}</div>
            <div class="meta">{{ $product->id }}</div>
        </div>
    </a>
    @endforeach
</div>