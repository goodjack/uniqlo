@inject('productPresenter', 'App\Presenters\ProductPresenter')

<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts narrow container">
        <div class="ts doubling link cards four">
            @foreach ($products as $product)
            <a class="ts flatted card" href="{{ $productPresenter->getProductUrl($product) }}">
                <div class="image">
                    <img src="{{ $product->main_image_url }}">
                </div>
                <div class="content">
                    <div class="smaller header">{{ $product->name }}</div>
                    <div class="meta">{{ $product->id }}</div>
                </div>
                <div class="center aligned extra content">
                    NT${{ $product->price }}
                </div>
            </a>
            @endforeach
        </div>
    </div>
</div>