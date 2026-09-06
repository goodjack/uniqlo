@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    /**
     * 商品的狀態標籤。順序與文案由 HmallProductPresenter::getProductTags() 決定，
     * 卡片與收藏列表共用，兩邊都全部顯示——收起兩個以外的標籤那版被站主判定
     * 是退步（高低不齊比漏資訊好接受），這裡拿掉上限。
     */
    $tags = $hmallProductPresenter->getProductTags($hmallProduct);
@endphp

@if (!empty($tags))
    {{-- 不掛 Tocas 的 .description：.ts.card>.content>.meta+.description 會用 .85em 的 --}}
    {{-- margin 蓋掉這裡的間距，首頁那種沒有價格列的卡片就會跟 meta 黏在一起 --}}
    <div class="uq-card-labels">
        @foreach ($tags as $tag)
            <span class="ts mini basic label uq-label @if ($tag['price']) uq-label-price @endif"
                @isset($tag['title']) title="{{ $tag['title'] }}" @endisset>{{ $tag['text'] }}</span>
        @endforeach
    </div>
@endif
