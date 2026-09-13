{{--
    空狀態。清單頁、分類頁、搜尋結果與收藏頁共用同一塊 markup。

    icon        可選，Tocas 的 icon 類別，不含 icon 本身（例：'search faded'）。
                不傳就完全不輸出圖示，標題也不掛 icon 這個變體——留給已經有
                別的視覺元素講過同一件事的狀態用（收藏頁「還沒有收藏任何
                商品」：頁首那顆紅愛心已經講完這是收藏頁，不需要再放一顆）
    title       現在是什麼狀況
    hint        使用者接下來可以做什麼
    slot        可選，狀態底下的按鈕
    attributes  可選，原樣輸出到最外層；收藏頁靠 id 與 hidden 切換三種狀態
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
