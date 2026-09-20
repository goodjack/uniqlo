{{--
    收藏清單每一列的標籤：哪一家、它現在的狀態。移除鈕搬到 item.blade.php 的
    .actions（見 favorites/item-remove-button.blade.php，從 cards.blade.php
    當 'actions' 傳進去），不在這個插槽裡了——那顆鈕要排在列的最右側，跟品牌
    角標、狀態標籤不同欄。

    favorites-labels 這個 class 是給 app.css 掛勾的：品牌角標跟
    card-labels 裡的狀態標籤原本各自佔一行（角標是 .ts.mini.label，狀態標籤
    包在 .description 這個 block 元素裡），app.css 用它把兩者併進同一個
    可換行的區域。
--}}
<div class="extra favorites-labels">
    <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif label">
        {{ $hmallProduct->brand }}
    </div>
    @include('hmall-products.partials.card-labels', ['hmallProduct' => $hmallProduct])
</div>
