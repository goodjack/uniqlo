@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    $useJapanRating ??= false;
@endphp

<a class="ts flatted card" href="{{ $hmallProduct->route_url }}">
    <div class="image">
        <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
        <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif top right attached label">
            {{ $hmallProduct->brand }}</div>
    </div>
    <div class="content">
        <div class="smaller header">{{ $hmallProductPresenter->getFullNameWithCode($hmallProduct) }}</div>
        <div class="middoted meta">
            <span>{{ $hmallProduct->short_product_code }}</span>
            {!! $hmallProductPresenter->getRatingForProductCardAndItem($hmallProduct, $useJapanRating) !!}
            {!! $hmallProductPresenter->getVideoIconForProductCardAndItem($hmallProduct) !!}
        </div>
    </div>
    <div class="center aligned extra content">
        <div class="smaller header">
            ${{ $hmallProduct->price }}
            @if ($hmallProduct->highest_record_price !== $hmallProduct->lowest_record_price)
                <div class="sub header">
                    ${{ (int) $hmallProduct->highest_record_price }}
                    -
                    @if ($hmallProduct->price > $hmallProduct->lowest_record_price)
                        <span style="color: #8BB96E;">
                            ${{ (int) $hmallProduct->lowest_record_price }}
                        </span>
                    @else
                        ${{ (int) $hmallProduct->lowest_record_price }}
                    @endif
                </div>
            @endif
        </div>
        <div class="description">
            @if ($hmallProduct->is_limited_offer || $hmallProduct->is_app_offer || $hmallProduct->is_ec_only)
                <div class="ts horizontal basic circular label">
                    <span style="color: #CE5F58;">
                        {{ $hmallProductPresenter->getLimitedOfferMessage($hmallProduct) }}
                    </span>
                </div>
            @endif

            @if ($hmallProduct->is_app_offer)
                <div class="ts horizontal basic circular label">
                    <span style="color: #CE5F58;">APP 限定特價</span>
                </div>
            @endif

            @if ($hmallProduct->is_ec_only)
                <div class="ts horizontal basic circular label">
                    <span style="color: #CE5F58;">網路限定特價</span>
                </div>
            @endif

            @if ($hmallProduct->is_sale)
                <div class="ts horizontal basic circular label">
                    <span style="color: #00ADEA;">特價商品</span>
                </div>
            @endif

            @if ($hmallProduct->is_new)
                <div class="ts horizontal basic circular label">
                    <span style="color: #8BB96E;">新款商品</span>
                </div>
            @endif

            @if ($hmallProduct->is_coming_soon)
                <div class="ts horizontal basic circular label">
                    <span style="color: #50723C;">即將上市</span>
                </div>
            @endif

            @if ($hmallProduct->is_multi_buy)
                <div class="ts horizontal basic circular label">
                    <span style="color: #79A8B9;">合購商品</span>
                </div>
            @endif

            @if ($hmallProduct->is_online_special)
                <div class="ts horizontal basic circular label">
                    <span style="color: #F29E18;">網路獨家販售</span>
                </div>
            @endif

            @if ($hmallProduct->is_stockout)
                <div class="ts horizontal basic circular label">
                    <span style="color: #5A5A5A;">已售罄</span>
                </div>
            @endif
        </div>
    </div>
</a>
