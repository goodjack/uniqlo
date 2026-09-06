@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    $useJapanRating ??= false;
@endphp

{{--
    版面回到 Tocas .ts.card 的預設，也就是 master 的排法：圖片 →
    品名（.smaller.header）→ 元資料（.middoted.meta：適穿、貨號、評分、影片）
    → $slot（價格與狀態標籤）。中點分隔、字級與間距全部交給 Tocas。

    只有一件事不能照 master：master 整張卡片是一個 <a>，收藏鈕放進去會變成
    巢狀互動元素（無效的 HTML，無障礙樹也讀不出「這是另一顆按鈕」）。所以外層
    是 div，第一個子元素是一條覆蓋整張卡片的連結負責點擊，收藏鈕是它的兄弟
    節點。.ts.card 仍然是 .ts.cards 的直接子元素，Tocas 的版面規則整組照樣套得到。
--}}
<div class="ts borderless card uq-card" data-card-name="{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}">
    <a class="uq-card-link" href="{{ $hmallProduct->route_url }}"
        aria-label="{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}"></a>
    <div class="image">
        <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
        <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif top right attached label">
            {{ $hmallProduct->brand }}</div>
    </div>
    <div class="content">
        @include('partials.favorite-toggle', ['hmallProduct' => $hmallProduct])
        <div class="smaller header">{{ $hmallProductPresenter->getNameWithCode($hmallProduct) }}</div>
        <div class="middoted meta">
            {{-- 適穿是空字串的商品有一批，空的 <span> 會讓 .middoted 多畫一個中點 --}}
            @if (filled($hmallProduct->sex))
                <span>{{ $hmallProduct->sex }}@if ($hmallProduct->is_unisex)/男女適穿@endif</span>
            @endif
            <span>{{ $hmallProduct->short_product_code }}</span>
            {!! $hmallProductPresenter->getRatingForProductCardAndItem($hmallProduct, $useJapanRating) !!}
            {!! $hmallProductPresenter->getVideoIconForProductCardAndItem($hmallProduct) !!}
        </div>
        {!! $slot ?? '' !!}
    </div>
</div>
