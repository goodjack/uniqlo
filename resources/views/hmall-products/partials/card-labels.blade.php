@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    /**
     * 商品的狀態標籤。順序、文案與顏色由 HmallProductPresenter::getProductTags() 決定，
     * 卡片與收藏列表共用，兩邊都全部顯示——收起兩個以外的標籤那版被站主判定
     * 是退步（高低不齊比漏資訊好接受），這裡拿掉上限。
     *
     * 顏色是 master 的一色一義（期間限定紅、特價與歷史新低同一個藍、新款綠……），
     * 寫成 inline style 跟 master 同一個做法；v3 那版把它壓成「價格類暗紅、其餘灰」
     * 兩色，等於把七種狀態的差別抹掉。
     *
     * 邊框維持 v3 拿掉的狀態：Tocas 的 .ts.mini.basic.label 那層框（實際只有 9px）
     * 讓次要資訊比品名、現價還搶眼。
     */
    $tags = $hmallProductPresenter->getProductTags($hmallProduct);
@endphp

@if (!empty($tags))
    <div class="uq-card-status">
        @foreach ($tags as $tag)
            <span class="uq-card-status-item" style="color: {{ $tag['color'] }};">{{ $tag['text'] }}</span>
        @endforeach
    </div>
@endif
