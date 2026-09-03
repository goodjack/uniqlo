@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

{{--
    整列原本是一個 <a>，於是列裡面的任何操作按鈕都會變成巢狀互動元素——那是
    無效的 HTML，無障礙樹裡也讀不出「這是另一顆按鈕」。改成整列是 div，圖片與
    標題各自是連結，slot 裡的按鈕就是連結的兄弟節點而不是子孫。

    .item 仍然是 .ts.items 的直接子元素、.image 與 .content 仍然是 .item 的直接
    子元素，Tocas 的版面規則（含 divided 的分隔線）整組照樣套得到。
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
</div>
