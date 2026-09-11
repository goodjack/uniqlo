{{-- 首頁的一個商品區塊：標題、看全部連結、一排卡片 grid。 --}}
<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        {{-- 區塊標題全站一種：Tocas 的 ts large header 加 inline sub header，跟 master 一樣 --}}
        <h2 class="ts large header">
            <i class="{{ $section['style'] }} {{ $section['icon'] }} icon"></i>
            {{ $section['title'] }}
            <div class="inline sub header">{{ $section['subtitle'] }}</div>
            {{-- 照 master 商品頁那組標題的做法，動作鈕排在副標後面。Tocas 的
                 .ts.header 是 flex 容器，子元素吃不到 float，所以它就排在同一行的
                 尾巴，不另外寫規則把它推到最右邊 --}}
            <a class="ts right floated button" href="{{ route($section['route']) }}">看全部</a>
        </h2>

        <div class="ts doubling cards six uq-product-cards">
            @each('hmall-products.simple-card', $section['products'], 'hmallProduct')
        </div>
    </div>
</div>
