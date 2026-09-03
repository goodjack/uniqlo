{{-- 首頁的一個商品區塊：標題、看全部連結、可橫向捲動的卡片列。 --}}
{{-- 樣式在 public/css/app.css。 --}}
<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        <div class="home-section-header">
            <h2 class="ts large header">
                <i class="{{ $section['style'] }} {{ $section['icon'] }} icon"></i>
                {{ $section['title'] }}
                <div class="inline sub header">{{ $section['subtitle'] }}</div>
            </h2>
            <a class="ts mini compact basic button" href="{{ route($section['route']) }}">
                看全部
            </a>
        </div>

        <div class="ts link cards home-product-row">
            @each('hmall-products.simple-card', $section['products'], 'hmallProduct')
        </div>
    </div>
</div>
