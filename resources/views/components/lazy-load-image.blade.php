@if ($priority)
    <img src="{{ $src }}" alt="{{ $alt }}" fetchpriority="high" {{ $attributes }}
        style="aspect-ratio: {{ $width }} / {{ $height }}; object-fit: cover;">
@else
    <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" {{ $attributes }}
        style="aspect-ratio: {{ $width }} / {{ $height }}; object-fit: cover;">
@endif
