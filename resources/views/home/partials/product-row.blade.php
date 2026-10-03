<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        <h2 class="ts large header">
            <i class="{{ $section['style'] }} {{ $section['icon'] }} icon"></i>
            {{ $section['title'] }}
            <div class="inline sub header">{{ $section['subtitle'] }}</div>
            <a class="ts right floated button uq-header-action"
                href="{{ route($section['route']) }}">看全部</a>
        </h2>

        <div class="ts doubling cards six uq-product-cards">
            @each('hmall-products.simple-card', $section['products'], 'hmallProduct')
        </div>
    </div>
</div>
