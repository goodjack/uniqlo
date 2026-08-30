{{-- 收藏頁的清單片段，由前端帶著品牌與商品編號來換。 --}}
{{-- 用 item 而不是卡片：收藏是一份清單，緊湊的列表比一格格的卡片好掃。 --}}
{{-- 不吐價格，只留「這件現在特價」這種狀態標籤——那才是使用者追蹤它的原因。 --}}
@foreach ($hmallProducts as $hmallProduct)
    @include('hmall-products.item', [
        'hmallProduct' => $hmallProduct,
        'itemAttributes' =>
            'data-favorite-key="' . e($hmallProduct->brand . ':' . $hmallProduct->product_code) . '"',
        'slot' => view('favorites.item-labels', ['hmallProduct' => $hmallProduct]),
    ])
@endforeach
