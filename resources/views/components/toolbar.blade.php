{{--
    一行工具列。start 放換一批商品的導覽型控制（品牌切換），end 放改變同一批商品
    呈現方式的條件型控制，順序固定是搜尋、排序、篩選；預設插槽是工具列下方跨整行
    的一列（篩選面板）。做成元件而不是 partial，是因為兩側收的是整塊 markup。
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
