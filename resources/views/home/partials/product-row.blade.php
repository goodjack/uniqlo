{{-- 首頁的一個商品區塊：標題、看全部連結、可橫向捲動的卡片列。 --}}
{{-- 樣式在 public/css/app.css。 --}}
<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        {{-- 跟分類總覽的群組標題同一種樣子，區塊標題全站一種 --}}
        {{-- 「看全部」放進標題裡：.uq-h2 那條線要在它下面，不然按鈕會孤零零掛在線外 --}}
        <h2 class="unstyled uq-h2">
            <i class="{{ $section['style'] }} {{ $section['icon'] }} icon"></i>
            {{ $section['title'] }}
            <span class="uq-count">{{ $section['subtitle'] }}</span>
            <a class="ts mini basic button uq-h2-action" href="{{ route($section['route']) }}">看全部</a>
        </h2>

        <div class="ts link cards home-product-row">
            @each('hmall-products.simple-card', $section['products'], 'hmallProduct')
        </div>
    </div>
</div>
