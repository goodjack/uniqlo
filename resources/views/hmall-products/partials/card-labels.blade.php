@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    // 順序、文案與顏色由 HmallProductPresenter::getProductTags() 決定，卡片與收藏列表共用
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
