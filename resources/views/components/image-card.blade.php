<a class="ts card" href="{{ $largeImageUrl }}" rel="nofollow noopener" data-lightbox="image"
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

    @if (isset($colorHeader))
        <div class="overlapped content color-header">{{ $colorHeader }}</div>
    @endif
</a>
