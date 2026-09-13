{{--
    收藏清單每一列的移除鈕，當成 hmall-products.item 的 $actions 傳進去、
    渲染在 .item > .actions（列的最右側），不是內容欄裡的東西。

    整列不再是一個 <a>，這裡就能用真的 <button>：焦點、Enter 與空白鍵都由
    瀏覽器處理，不用另外寫 keydown。跟工具列「清空收藏」用同一個
    ts basic compact button 尺寸（約 35.5px、字 14px），維持中性灰、不用
    品牌紅——這顆是列表裡的次要操作，不是找到商品後的主要行動。
--}}
<button type="button" class="ts basic compact button" data-favorite-remove
    data-brand="{{ $hmallProduct->brand }}" data-code="{{ $hmallProduct->product_code }}">
    <i class="close icon"></i>移除收藏
</button>
