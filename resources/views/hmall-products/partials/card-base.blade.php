@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    $useJapanRating ??= false;
@endphp

<a class="ts borderless card" href="{{ $hmallProduct->route_url }}">
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
</a>
