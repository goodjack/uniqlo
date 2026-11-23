{{--
    商品頁各段的段標題，每一段都用這個，外觀才會一致。
    section 是 GA 段落曝光的代號（data-section-view）；sub 是標題旁的灰字（張數、項目）。
    右側按鈕（看更多、逛全部這類）一律用首頁「看全部」同一種按鈕：給 actionHref 是連結，
    沒給是按鈕，由頁面腳本照 actionData 的 data 屬性接手。actionIcon 可選（Tocas 圖示名，例如 camera retro），
    給了才用 labeled icon button 在左側加圖示，大小與顏色跟沒圖示的一樣。icon 插槽放標題前的圖示（例如收合箭頭）。
    標題到內容的距離由標題自己的下距負責，後面不要再接 hidden divider。
--}}
@props([
    'section',
    'sub' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'actionIcon' => null,
    'actionData' => [],
])

<h2 {{ $attributes->merge(['class' => 'ts big header uq-section-header']) }} data-section-view="{{ $section }}">
    {{ $icon ?? '' }}{{ $slot }}
    @if (filled($sub))
        <div class="inline sub header">{{ $sub }}</div>
    @endif
    @if ($actionLabel)
        @php($actionClass = 'ts right floated' . ($actionIcon ? ' labeled icon' : '') . ' button uq-header-action')
        @if ($actionHref)
            <a class="{{ $actionClass }}" href="{{ $actionHref }}">@if ($actionIcon)<i class="{{ $actionIcon }} icon" aria-hidden="true"></i>@endif{{ $actionLabel }}</a>
        @else
            <button type="button" class="{{ $actionClass }}"
                @foreach ($actionData as $name => $value) {{ $name }}="{{ $value }}" @endforeach>@if ($actionIcon)<i class="{{ $actionIcon }} icon" aria-hidden="true"></i>@endif{{ $actionLabel }}</button>
        @endif
    @endif
</h2>
