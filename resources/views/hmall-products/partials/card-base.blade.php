@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    $useJapanRating ??= false;
@endphp

{{--
    整張卡片原本是一個 <a>，於是卡片上的收藏鈕會變成巢狀互動元素。改成整張是
    div，第一個子元素是一條覆蓋整張卡片的連結負責點擊，收藏鈕是適穿列裡的
    兄弟節點。

    .ts.card 仍然是 .ts.cards 的直接子元素，Tocas 的版面規則整組照樣套得到；
    hover 的陰影也還在，因為它掛的是 .ts.link.cards .card:hover，不是 a.card:hover。

    v3 版的資訊層級（由上到下）：圖片 → 適穿列（含收藏愛心）→ 品名 → 價格與
    狀態行（$slot，卡片各異：完整卡有價格加狀態、簡卡只有狀態）→ 末行（評分
    加編號）。基準是官網清單頁，主要資訊（品名、現價）字級字重都比次要資訊
    （適穿、狀態文字、末行）重，次要資訊一律不掛邊框——細節見 app.css 卡片
    段落開頭的說明。
--}}
<div class="ts borderless card uq-card" data-card-name="{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}">
    <a class="uq-card-link" href="{{ $hmallProduct->route_url }}"
        aria-label="{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}"></a>
    <div class="image">
        <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
        <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif top right attached label uq-card-brand">
            {{ $hmallProduct->brand }}</div>
    </div>
    <div class="content">
        <div class="uq-card-row">
            @if (filled($hmallProduct->sex))
                <span class="uq-card-fit">{{ $hmallProduct->sex }}@if ($hmallProduct->is_unisex)/男女適穿@endif</span>
            @endif
            @include('partials.favorite-toggle', ['hmallProduct' => $hmallProduct])
        </div>
        <div class="uq-card-name">{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}</div>
        {!! $slot ?? '' !!}
        <div class="uq-card-tail">
            {!! $hmallProductPresenter->getRatingForProductCardAndItem($hmallProduct, $useJapanRating) !!}
            {!! $hmallProductPresenter->getVideoIconForProductCardAndItem($hmallProduct) !!}
            <span>{{ $hmallProduct->short_product_code }}</span>
        </div>
    </div>
</div>
