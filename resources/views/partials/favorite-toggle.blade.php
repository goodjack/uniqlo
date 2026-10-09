@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

{{--
    卡片上的收藏鈕。不能放進卡片的覆蓋連結裡（巢狀互動元素），所以是連結的兄弟
    節點，靠 app.css 的 z-index 浮在覆蓋連結上面。沒有看得到的文字，可及名稱要
    帶商品名，一頁幾十顆才分得出來；狀態交給 aria-pressed。
--}}
<button type="button" class="uq-card-heart" data-favorite-card
    data-brand="{{ $hmallProduct->brand }}" data-product-code="{{ $hmallProduct->product_code }}"
    aria-pressed="false" aria-label="收藏 {{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}">
    <i class="heart outline icon" aria-hidden="true"></i>
</button>
