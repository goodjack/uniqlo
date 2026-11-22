{{-- 沒給寬高時照原圖比例顯示，不裁切 --}}
@if ($priority)
    <img src="{{ $src }}" alt="{{ $alt }}" fetchpriority="high" {{ $attributes }}
        @if ($width && $height) style="aspect-ratio: {{ $width }} / {{ $height }}; object-fit: cover;" @endif>
@else
    <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" {{ $attributes }}
        @if ($width && $height) style="aspect-ratio: {{ $width }} / {{ $height }}; object-fit: cover;" @endif>
@endif
