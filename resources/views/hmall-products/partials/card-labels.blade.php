@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')
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

    @if ($hmallProduct->is_new_historical_low)
        <div class="ts horizontal basic circular label">
            <span style="color: #00ADEA;">歷史新低價</span>
        </div>
    @endif

    @if ($hmallProduct->is_most_visited)
        <div class="ts horizontal basic circular label">
            <span style="color: #B58105;">
                瀏覽 TOP {{ $hmallProduct->most_visited_rank }}
            </span>
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
