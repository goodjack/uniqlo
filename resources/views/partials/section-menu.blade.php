@php
    /**
     * 章節選單，sticky 在導覽列下方，active 由 master 版型初始化的 scrollspy 切換。
     *
     * id     選單的 id，每一段的錨點要有同名的 data-scrollspy
     * items  ['anchor', 'label', 'count'?] 或分段小標 ['heading']
     *
     * 放在頁面 .ts.container 外面（自帶一層），白底與底線才會滿版。
     * 只有一段時不渲染。
     */
    $anchorCount = collect($items)->filter(fn($item) => isset($item['anchor']))->count();
@endphp

@if ($anchorCount > 1)
    <div class="uq-section-menu">
        <div class="ts container">
            <div class="ts large compact pointing secondary menu" id="{{ $id }}">
                @foreach ($items as $item)
                    @isset($item['heading'])
                        <div class="header item">{{ $item['heading'] }}</div>
                    @else
                        <a class="item" href="#{{ $item['anchor'] }}">
                            {{ $item['label'] }}
                            @isset($item['count'])
                                <div class="ts tiny circular label">{{ $item['count'] }}</div>
                            @endisset
                        </a>
                    @endisset
                @endforeach
            </div>
        </div>
    </div>
@endif
