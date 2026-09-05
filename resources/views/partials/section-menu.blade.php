@php
    /**
     * 章節選單。sticky 在導覽列下方，捲動時由 tocas.js 的 scrollspy 把 active
     * 換到目前看到的那一段（初始化在 master 版型）。
     *
     * id     選單的 id。每一段的錨點元素要有同名的 data-scrollspy 屬性
     * items  陣列，每一筆是 ['anchor' => string, 'label' => string, 'count' => ?int]
     * fluid  選單自己橫跨整頁寬度時傳 true，裡面才補一層 ts container
     *
     * sticky 只在自己的父元素框內有效，所以黏住的是外層那個 div 而不是選單本身：
     * 清單頁與分類總覽的它住在 ts container 裡（那個 container 涵蓋整頁內容），
     * 商品頁的則是內容區的直接子元素、寬度靠裡面的 container 收。
     *
     * 只有一段時不渲染：一個項目的選單點了也不會去別的地方。
     */
    $fluid = $fluid ?? false;
@endphp

@if (count($items) > 1)
    <div class="uq-section-menu">
        <div class="{{ $fluid ? 'ts container' : 'uq-section-menu-inner' }}">
            <div class="ts pointing secondary menu" id="{{ $id }}">
                @foreach ($items as $item)
                    <a class="item" href="#{{ $item['anchor'] }}">
                        {{ $item['label'] }}
                        @isset($item['count'])
                            <span class="uq-count">{{ $item['count'] }}</span>
                        @endisset
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
