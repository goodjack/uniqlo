@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    // 順序、文案與顏色由 HmallProductPresenter::getProductTags() 決定，卡片與收藏列表共用。
    // 卡片一個標籤一行（Tocas 的直排清單，small 比分類、評分小一號）；收藏列表傳 inline，跟品牌角標排在同一行
    $tags = $hmallProductPresenter->getProductTags($hmallProduct);
    $inline ??= false;
@endphp

@if (!empty($tags))
    <div class="description">
        @if ($inline)
            @foreach ($tags as $tag)
                <div class="ts horizontal basic borderless circular label">
                    <span style="color: {{ $tag['color'] }};">{{ $tag['text'] }}</span>
                </div>
            @endforeach
        @else
            <div class="ts small list">
                @foreach ($tags as $tag)
                    <div class="item"><span style="color: {{ $tag['color'] }};">{{ $tag['text'] }}</span></div>
                @endforeach
            </div>
        @endif
    </div>
@endif
