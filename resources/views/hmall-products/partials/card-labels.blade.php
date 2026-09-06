@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    /**
     * 商品的狀態標籤。順序與文案由 HmallProductPresenter::getProductTags() 決定，
     * 卡片與收藏列表共用，兩邊都全部顯示——收起兩個以外的標籤那版被站主判定
     * 是退步（高低不齊比漏資訊好接受），這裡拿掉上限。
     *
     * v3 版把每一項從 Tocas 的 .ts.mini.basic.label 邊框改成純文字行：GPT-6
     * Astra 對照官網抓出的問題之一就是這層邊框（.ts.mini.label 實際只有
     * 9px）讓次要資訊反而比主要資訊（品名、現價）搶眼。優惠類（tag.price 為
     * 真）用優惠色 13px，其餘一律灰階 12px，樣式見 app.css 的 .uq-card-status。
     */
    $tags = $hmallProductPresenter->getProductTags($hmallProduct);
@endphp

@if (!empty($tags))
    <div class="uq-card-status">
        @foreach ($tags as $tag)
            <span class="uq-card-status-item @if ($tag['price']) uq-card-status-price @endif"
                @isset($tag['title']) title="{{ $tag['title'] }}" @endisset>{{ $tag['text'] }}</span>
        @endforeach
    </div>
@endif
