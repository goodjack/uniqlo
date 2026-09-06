@php
    /**
     * 章節選單。sticky 在導覽列下方，捲動時由 tocas.js 的 scrollspy 把 active
     * 換到目前看到的那一段（初始化在 master 版型）。
     *
     * id     選單的 id。每一段的錨點元素要有同名的 data-scrollspy 屬性
     * items  陣列，每一筆是 ['anchor' => string, 'label' => string, 'count' => ?int]，
     *        或者 ['heading' => string]——那是分段用的小標（分類總覽的品牌名），
     *        不是連結，scrollspy 只認 a[href='#anchor']，掃不到它也不會挑中它
     *
     * 這個 partial 一律站在 .ts.container 外面、自己再包一層 .ts.container：
     * 白底跟底線才會滿版，不是只跨中間那欄。呼叫端不要把它塞進別的 container
     * 裡面——清單頁、分類總覽、商品頁三邊都把它擺在頁面內容 container 的
     * 外側，各自的第二層 container 才是實際內容。
     *
     * 只有一段時不渲染：一個項目的選單點了也不會去別的地方（分段小標不算一段）。
     */
    $anchorCount = collect($items)->filter(fn($item) => isset($item['anchor']))->count();
@endphp

@if ($anchorCount > 1)
    <div class="uq-section-menu">
        <div class="ts container">
            <div class="ts pointing secondary menu" id="{{ $id }}">
                @foreach ($items as $item)
                    @isset($item['heading'])
                        <span class="uq-menu-heading">{{ $item['heading'] }}</span>
                    @else
                        <a class="item" href="#{{ $item['anchor'] }}">
                            {{ $item['label'] }}
                            @isset($item['count'])
                                <span class="uq-count">{{ $item['count'] }}</span>
                            @endisset
                        </a>
                    @endisset
                @endforeach
            </div>
        </div>
    </div>
@endif
