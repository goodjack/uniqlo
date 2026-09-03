{{--
    一行工具列。控制項全部住在這裡，slate 只留標題。

    start   左側：導覽型控制（品牌切換、子分類），換一個就是換一批商品
    end     右側：條件型控制（排序、篩選），改的是同一批商品的呈現方式
    預設插槽  工具列下方跨整行的一列，目前只有篩選 chip 列用它

    做成 Blade 匿名元件而不是 partial，是因為這兩側收的是一整塊 markup；
    @include 只能傳字串，呼叫端會被逼著先把 view render 成字串再串起來。

    390 寬時左右各自成一排（app.css 的 .uq-toolbar）。
--}}
@props(['start' => null, 'end' => null])

<div class="uq-toolbar">
    @if ($start)
        <div class="uq-toolbar-start">{{ $start }}</div>
    @endif

    @if ($end)
        <div class="uq-toolbar-end">{{ $end }}</div>
    @endif

    {{ $slot }}
</div>
