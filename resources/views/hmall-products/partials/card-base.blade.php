@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    $useJapanRating ??= false;
@endphp

{{--
    整張卡片原本是一個 <a>，於是卡片上的收藏鈕會變成巢狀互動元素。改成整張是
    div，第一個子元素是一條覆蓋整張卡片的連結負責點擊，收藏鈕是它的兄弟節點。

    .ts.card 仍然是 .ts.cards 的直接子元素，Tocas 的版面規則整組照樣套得到；
    hover 的陰影也還在，因為它掛的是 .ts.link.cards .card:hover，不是 a.card:hover。
--}}
<div class="ts borderless card uq-card">
    <a class="uq-card-link" href="{{ $hmallProduct->route_url }}"
        aria-label="{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}"></a>
    <div class="image">
        <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
        <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif top right attached label">
            {{ $hmallProduct->brand }}</div>
    </div>
    <div class="content">
        <div class="smaller header">{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}</div>
        <div class="middoted meta">
            <span>{{ $hmallProduct->sex }}</span>
            <span>{{ $hmallProduct->short_product_code }}</span>
            {!! $hmallProductPresenter->getRatingForProductCardAndItem($hmallProduct, $useJapanRating) !!}
            {!! $hmallProductPresenter->getVideoIconForProductCardAndItem($hmallProduct) !!}
        </div>
        {!! $slot ?? '' !!}
    </div>
    @include('partials.favorite-toggle', ['hmallProduct' => $hmallProduct])
</div>
