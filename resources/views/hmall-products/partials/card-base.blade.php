@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    $useJapanRating ??= false;
@endphp

{{--
    外層不是 <a>：收藏鈕不能是連結的子孫，點擊交給蓋滿卡片的 .uq-card-link。

    data-card-search 給 list-search.js 的即時篩用，欄位要跟
    ListService::hmallProductMatchesKeyword() 比對的一致。
--}}
<div class="ts borderless card uq-card" data-card-name="{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}"
    data-card-search="{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }} {{ $hmallProduct->product_code }} {{ $hmallProduct->short_product_code }}">
    <a class="uq-card-link" href="{{ $hmallProduct->route_url }}"
        aria-label="{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}"></a>
    <div class="image">
        <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
        <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif top right attached label">
            {{ $hmallProduct->brand }}</div>
    </div>
    <div class="content">
        @include('partials.favorite-toggle', ['hmallProduct' => $hmallProduct])
        <div class="smaller header">{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}</div>
        <div class="middoted meta">
            {{-- 適穿是空字串的商品有一批，空的 <span> 會讓 .middoted 多畫一個中點 --}}
            @if (filled($hmallProduct->sex))
                <span>{{ $hmallProduct->sex }}@if ($hmallProduct->is_unisex)/男女適穿@endif</span>
            @endif
            <span>{{ $hmallProduct->short_product_code }}</span>
            {!! $hmallProductPresenter->getRatingForProductCardAndItem($hmallProduct, $useJapanRating) !!}
            {!! $hmallProductPresenter->getVideoIconForProductCardAndItem($hmallProduct) !!}
        </div>
        {!! $slot ?? '' !!}
    </div>
</div>
