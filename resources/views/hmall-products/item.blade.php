@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

<a class="item" href="{{ $hmallProduct->route_url }}">
    <div class="ts tiny image">
        <img data-src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}" class="lazyload"
            loading="lazy"
            alt="{{ $hmallProductPresenter->getFullNameWithCode($hmallProduct) }} {{ $hmallProduct->product_code }}">
    </div>
    <div class="middle aligned content">
        <div class="header">
            {{ $hmallProductPresenter->getFullNameWithCode($hmallProduct) }}
        </div>
        <div class="middoted meta">
            <span>{{ $hmallProduct->product_code }}</span>
            {!! $hmallProductPresenter->getRatingForProductCardAndItem($hmallProduct) !!}
        </div>
        @if ($hmallProduct->is_stockout)
            <div class="extra">已售罄</div>
        @endif
    </div>
</a>
