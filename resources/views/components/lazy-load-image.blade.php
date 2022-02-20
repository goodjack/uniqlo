<img data-src="{{ $src }}" alt="{{ $alt }}" loading="lazy"
    src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {{ $width }} {{ $height }}'%3E%3C/svg%3E"
    {{ $attributes->merge(['class' => 'lazyload']) }}>
