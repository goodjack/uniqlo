{{--
    空狀態。
    icon        可選，Tocas icon 類別（例：'search faded'）
    title       現在是什麼狀況
    hint        接下來可以做什麼
    slot        可選，底下的按鈕
    attributes  可選，原樣輸出到最外層（未跳脫，只能放伺服器端組好的屬性）
--}}
<div class="ts center aligned basic segment" {!! $attributes ?? '' !!}>
    <div class="ts {{ isset($icon) ? 'icon ' : '' }}header">
        {!! isset($icon) ? '<i class="' . e($icon) . ' icon"></i>' : '' !!}
        <div class="content">
            {{ $title }}
            <div class="sub header">{{ $hint }}</div>
        </div>
    </div>
    {!! $slot ?? '' !!}
</div>
