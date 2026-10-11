@php
    /**
     * 章節選單，sticky 在導覽列下方，active 由 master 版型初始化的 scrollspy 切換。
     *
     * id     選單的 id，每一段的錨點要有同名的 data-scrollspy
     * items  ['anchor', 'label', 'count'?] 或分段小標 ['heading']
     * revealAfter  選填，某個區塊的 id：該區塊捲出畫面後選單才從導覽列下方滑出，
     *              平常不佔版面（購物 App 商品頁的慣例）；沒有 JS 時退回 sticky
     *
     * 放在頁面 .ts.container 外面（自帶一層），白底與底線才會滿版。
     * 只有一段時不輸出。
     */
    $anchorCount = collect($items)->filter(fn($item) => isset($item['anchor']))->count();
    $revealAfter ??= null;
@endphp

@if ($anchorCount > 1)
    <div class="uq-section-menu" @if ($revealAfter) data-reveal-after="{{ $revealAfter }}" @endif>
        <div class="ts container">
            <div class="ts compact pointing secondary menu" id="{{ $id }}">
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
