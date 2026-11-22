@php($captionId = 'uq-caption-' . \Illuminate\Support\Str::random(12))
<a class="ts borderless card" href="{{ $largeImageUrl }}" rel="nofollow noopener" data-lightbox="{{ $album }}"
    data-description=".{{ $captionId }}" data-type="image"
    @if (!empty($link)) data-title="<a href='{{ $link }}' target='_blank' rel='nofollow noopener'>{{ $alt }}</a>"
    @else data-title="{{ $alt }}" @endif>
    <div class="image">
        <x-lazy-load-image src="{{ $imageUrl }}" alt="{{ $alt }}" width="{{ $width }}"
            height="{{ $height }}" />
        @if ($country === 'jp')
            <div class="ts mini top right attached label">日本版</div>
        @elseif ($country === 'us')
            <div class="ts mini top right attached label">美國版</div>
        @endif
    </div>

    {{-- 放大圖的說明（GLightbox 照 data-description 讀這裡），轉義過才不會被外部資料塞進標籤。
         data-type 要明講：穿搭照的大圖網址沒有副檔名，GLightbox 猜不出是圖片，會當成網頁嵌入 --}}
    <template class="{{ $captionId }}">
        @if (!empty($link))
            <a href="{{ $link }}" target="_blank" rel="nofollow noopener">{{ $alt }}</a>
        @else
            {{ $alt }}
        @endif
    </template>

    @if (isset($colorHeader))
        <div class="overlapped content color-header">{{ $colorHeader }}</div>
    @endif
</a>
