@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    /**
     * 商品的狀態標籤。順序、文案與顏色由 HmallProductPresenter::getProductTags() 決定，
     * 卡片與收藏列表共用，兩邊都全部顯示。
     *
     * 外觀回到 master 的 Tocas 原生標籤（.ts.horizontal.basic.circular.label），
     * 顏色照 master 寫成 inline style 的一色一義（期間限定紅、特價與歷史新低
     * 同一個藍、新款綠……）。唯一跟 master 不同的是那個藍：master 的 #00ADEA
     * 放在白底只有 2.4:1，小字讀不清，改用加深版（見 presenter 的顏色常數）。
     */
    $tags = $hmallProductPresenter->getProductTags($hmallProduct);
@endphp

@if (!empty($tags))
    <div class="description">
        @foreach ($tags as $tag)
            <div class="ts horizontal basic circular label">
                <span style="color: {{ $tag['color'] }};">{{ $tag['text'] }}</span>
            </div>
        @endforeach
    </div>
@endif
