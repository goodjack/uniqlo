@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

<a class="ts flatted card" href="{{ $hmallProduct->route_url }}">
    <div class="image">
        <img data-src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}" class="lazyload"
            loading="lazy"
            alt="{{ $hmallProductPresenter->getFullNameWithCode($hmallProduct) }} {{ $hmallProduct->product_code }}">
    </div>
    <div class="content">
        <div class="smaller header">{{ $hmallProductPresenter->getFullName($hmallProduct) }}</div>
        <div class="middoted meta">
            <span>{{ $hmallProduct->code }}</span>
            {!! $hmallProductPresenter->getRatingForProductCardAndItem($hmallProduct) !!}
        </div>
    </div>
    <div class="center aligned extra content">
        @if ($hmallProduct->highest_record_price > $hmallProduct->price)
            <small>${{ (int) $hmallProduct->highest_record_price }} ⇢</small>
            <strong>${{ $hmallProduct->price }}</strong>
        @else
            ${{ $hmallProduct->price }}
        @endif

        @if ($hmallProduct->price > $hmallProduct->lowest_record_price)
            <span style="color: #8BB96E;">⇢ ${{ (int) $hmallProduct->lowest_record_price }}</span>
        @endif

        @if ($hmallProduct->is_limited_offer)
            <br><span style="color: #CE5F58;">
                {{ $hmallProductPresenter->getLimitedOfferMessage($hmallProduct) }}
            </span>
        @endif

        @if ($hmallProduct->is_multi_buy)
            <br><span style="color: #79A8B9;">合購商品</span>
        @endif

        @if ($hmallProduct->is_sale)
            <br><span style="color: #00ADEA;">特價商品</span>
        @endif

        @if ($hmallProduct->is_new)
            <br><span style="color: #8BB96E;">新款商品</span>
        @endif

        @if ($hmallProduct->is_stockout)
            <br><span style="color: #5A5A5A;">已售罄</span>
        @endif
    </div>
    @if ($hmallProduct->price > $hmallProduct->lowest_record_price)
        <div class="symbol">
            <i class="caution circle icon"></i>
        </div>
    @endif
</a>
