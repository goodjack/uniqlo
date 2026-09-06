{{--
    空狀態。清單頁、分類頁、搜尋結果與收藏頁共用同一塊 markup。

    icon        Tocas 的 icon 類別，不含 icon 本身（例：'search faded'）
    title       現在是什麼狀況
    hint        使用者接下來可以做什麼
    slot        可選，狀態底下的按鈕
    attributes  可選，原樣輸出到最外層；收藏頁靠 id 與 hidden 切換三種狀態
--}}
<div class="ts center aligned basic segment" {!! $attributes ?? '' !!}>
    <div class="ts icon header">
        <i class="{{ $icon }} icon"></i>
        <div class="content">
            {{ $title }}
            <div class="sub header">{{ $hint }}</div>
        </div>
    </div>
    {!! $slot ?? '' !!}
</div>
