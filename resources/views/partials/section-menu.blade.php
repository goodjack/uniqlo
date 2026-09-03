{{--
    章節選單。sticky 在導覽列下方，捲動時由 Tocas 的 scrollspy 把 active 換到
    目前看到的那一段（初始化在 master 版型）。

    id     選單的 id。每一段的錨點元素要有同名的 data-scrollspy 屬性
    items  陣列，每一筆是 ['anchor' => string, 'label' => string, 'count' => ?int]

    只有一段時不渲染：一個項目的選單點了也不會去別的地方。
--}}
@if (count($items) > 1)
    <div class="ts small tabbed menu uq-section-menu" id="{{ $id }}">
        @foreach ($items as $item)
            <a class="item" href="#{{ $item['anchor'] }}">
                {{ $item['label'] }}
                @isset($item['count'])
                    <div class="ts mini circular label">{{ $item['count'] }}</div>
                @endisset
            </a>
        @endforeach
    </div>
@endif
