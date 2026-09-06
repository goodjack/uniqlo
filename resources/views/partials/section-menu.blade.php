@php
    /**
     * 章節選單。sticky 在導覽列下方，捲動時由 tocas.js 的 scrollspy 把 active
     * 換到目前看到的那一段（初始化在 master 版型）。
     *
     * id     選單的 id。每一段的錨點元素要有同名的 data-scrollspy 屬性
     * items  陣列，每一筆是 ['anchor' => string, 'label' => string, 'count' => ?int]
     *
     * 這個 partial 一律站在 .ts.container 外面、自己再包一層 .ts.container：
     * 白底跟底線才會滿版，不是只跨中間那欄。呼叫端不要把它塞進別的 container
     * 裡面——清單頁、分類總覽、商品頁三邊都把它擺在頁面內容 container 的
     * 外側，各自的第二層 container 才是實際內容。
     *
     * 只有一段時不渲染：一個項目的選單點了也不會去別的地方。
     */
@endphp

@if (count($items) > 1)
    <div class="uq-section-menu">
        <div class="ts container">
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
