@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

<a class="ts flatted card" href="{{ $hmallProduct->route_url }}">
    <div class="image">
        <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
    </div>
    <div class="content">
        <div class="smaller header">{{ $hmallProductPresenter->getFullNameWithCode($hmallProduct) }}</div>
        <div class="middoted meta">
            <span>{{ $hmallProduct->product_code }}</span>
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

        @if ($hmallProduct->is_limited_offer || $hmallProduct->is_app_offer || $hmallProduct->is_ec_only)
            <br><span style="color: #CE5F58;">
                {{ $hmallProductPresenter->getLimitedOfferMessage($hmallProduct) }}
            </span>
        @endif

        @if ($hmallProduct->is_app_offer)
            <br><span style="color: #CE5F58;">APP 限定特價</span>
        @endif

        @if ($hmallProduct->is_ec_only)
            <br><span style="color: #CE5F58;">網路限定特價</span>
        @endif

        @if ($hmallProduct->is_sale)
            <br><span style="color: #00ADEA;">特價商品</span>
        @endif

        @if ($hmallProduct->is_new)
            <br><span style="color: #8BB96E;">新款商品</span>
        @endif

        @if ($hmallProduct->is_coming_soon)
            <br><span style="color: #50723C;">即將上市</span>
        @endif

        @if ($hmallProduct->is_multi_buy)
            <br><span style="color: #79A8B9;">合購商品</span>
        @endif

        @if ($hmallProduct->is_online_special)
            <br><span style="color: #F29E18;">網路獨家販售</span>
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
