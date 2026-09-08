{{--
    一行工具列。控制項全部住在這裡，slate 只留標題。

    start   左側：導覽型控制，換一個就是換一批商品。目前只有清單頁的品牌切換，
            分類頁沒有這一類，整個 start 就不給
    end     右側：條件型控制，改的是同一批商品的呈現方式。搜尋、排序、篩選三個
            都算，順序固定是搜尋、排序、篩選；哪幾個有由呼叫端決定（分類頁沒有
            排序）。搜尋是條件型控制裡唯一「有些頁面才有」的那個，手機版它會自己
            佔滿一整排（app.css 的 .uq-toolbar-end:has(.uq-search-form)）
    預設插槽  工具列下方跨整行的一列，目前只有篩選 chip 列用它

    往別頁走的連結（分類頁的子分類列）不放進工具列：它換的是頁面、不是這一批
    商品的呈現方式，而且塞進 start 會把 end 整個擠到下一行。那一列住在麵包屑
    下面、工具列上面。

    做成 Blade 匿名元件而不是 partial，是因為這兩側收的是一整塊 markup；
    @include 只能傳字串，呼叫端會被逼著先把 view render 成字串再串起來。

    390 寬時左右各自成一排（app.css 的 .uq-toolbar）。
--}}
@props(['start' => null, 'end' => null])

<div {{ $attributes->merge(['class' => 'uq-toolbar']) }}>
    @if ($start)
        <div class="uq-toolbar-start">{{ $start }}</div>
    @endif

    @if ($end)
        <div class="uq-toolbar-end">{{ $end }}</div>
    @endif

    {{ $slot }}
</div>
