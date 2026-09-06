@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

{{--
    卡片上的收藏鈕。v3 版搬到圖片下面的適穿列右邊，不再疊在照片上——官網的
    卡片圖上乾乾淨淨，站主判定圓鈕壓在圖上是「次要資訊變多」的一部分。

    它不能放在卡片那條覆蓋連結裡（巢狀互動元素是無效的 HTML，無障礙樹也讀不出
    「這是另一顆按鈕」），所以是適穿列裡連結的兄弟節點——但整張卡片的覆蓋連結
    （.uq-card-link）是 position: absolute、z-index: 1，蓋住了包含這顆按鈕在內
    的整個 .content，所以 .uq-card-heart 還是得靠 position: relative、z-index: 2
    才點得到，樣式見 app.css。

    給 aria-label 是因為這顆按鈕沒有看得到的文字——跟商品頁那顆不一樣，那顆有
    「收藏／已收藏」可以當可及名稱。一頁上有幾十顆，名稱要帶商品名才分得出來；
    收藏與否交給 aria-pressed，favorites.js 會跟著切（favorites.js 認的是
    data-favorite-card 這個屬性，class 名稱怎麼改都不影響綁定）。
--}}
<button type="button" class="uq-card-heart" data-favorite-card
    data-brand="{{ $hmallProduct->brand }}" data-product-code="{{ $hmallProduct->product_code }}"
    aria-pressed="false" aria-label="收藏 {{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}">
    <i class="heart outline icon" aria-hidden="true"></i>
</button>
