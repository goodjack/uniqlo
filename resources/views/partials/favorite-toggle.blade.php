@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

{{--
    卡片上的收藏鈕。圖片左上角，品牌標籤維持右上角，兩者不重疊。

    它不能放在卡片那條覆蓋連結裡（巢狀互動元素是無效的 HTML，無障礙樹也讀不出
    「這是另一顆按鈕」），所以是連結的兄弟節點、z-index 比連結高。

    給 aria-label 是因為這顆按鈕沒有看得到的文字——跟商品頁那顆不一樣，那顆有
    「收藏／已收藏」可以當可及名稱。一頁上有幾十顆，名稱要帶商品名才分得出來；
    收藏與否交給 aria-pressed，favorites.js 會跟著切。
--}}
<button type="button" class="ts circular icon button uq-card-favorite" data-favorite-card
    data-brand="{{ $hmallProduct->brand }}" data-product-code="{{ $hmallProduct->product_code }}"
    aria-pressed="false" aria-label="收藏 {{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}">
    <i class="heart outline icon" aria-hidden="true"></i>
</button>
