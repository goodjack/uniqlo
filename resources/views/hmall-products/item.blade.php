@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

{{--
    整列不是 <a>：$actions 裡的按鈕不能是連結的子孫（巢狀互動元素）。
    $actions 選用，不傳就不輸出 .actions（商品頁的延伸商品沒有動作鈕）。
--}}
<div class="item" {!! $itemAttributes ?? '' !!}>
    <a class="ts tiny image" href="{{ $hmallProduct->route_url }}">
        <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
    </a>
    <div class="middle aligned content">
        <a class="header" href="{{ $hmallProduct->route_url }}">
            {{ $hmallProductPresenter->getFullNameWithCode($hmallProduct) }}
        </a>
        <div class="middoted meta">
            <span>{{ $hmallProduct->short_product_code }}</span>
            {!! $hmallProductPresenter->getRatingForProductCardAndItem($hmallProduct) !!}
            {!! $hmallProductPresenter->getVideoIconForProductCardAndItem($hmallProduct) !!}
        </div>
        @if ($hmallProduct->is_stockout)
            <div class="extra">已售罄</div>
        @endif
        {!! $slot ?? '' !!}
    </div>
@isset($actions)
    <div class="middle aligned right floated actions">
        {!! $actions !!}
    </div>
@endisset
</div>
