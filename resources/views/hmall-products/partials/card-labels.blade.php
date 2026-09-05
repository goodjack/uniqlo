@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

@php
    /**
     * 商品的狀態標籤。順序與文案由 HmallProductPresenter::getProductTags() 決定，
     * 卡片與收藏列表共用。
     *
     * tagLimit  最多顯示幾個，其餘收成一顆「+N」（滑過去用 title 看得到是哪些）。
     *           0 代表不限制，收藏列表用這個——那裡一列只有一件商品，攤開來看
     *           得完，也正是使用者追蹤它的原因。
     *
     * 一張卡片上七八個標籤的時候，第三個以後其實沒有人在讀，只是把品名跟價格
     * 往下推。留兩個：一個講價格、一個講其他，剛好是使用者掃卡片時要的資訊量。
     */
    $tagLimit ??= 2;
    $tags = $hmallProductPresenter->getProductTags($hmallProduct);

    $shown = $tagLimit > 0 ? array_slice($tags, 0, $tagLimit) : $tags;
    $hidden = $tagLimit > 0 ? array_slice($tags, $tagLimit) : [];
@endphp

@if (!empty($tags))
    {{-- 不掛 Tocas 的 .description：.ts.card>.content>.meta+.description 會用 .85em 的 --}}
    {{-- margin 蓋掉這裡的間距，首頁那種沒有價格列的卡片就會跟 meta 黏在一起 --}}
    <div class="uq-card-labels">
        @foreach ($shown as $tag)
            <span class="ts mini basic label uq-label @if ($tag['price']) uq-label-price @endif"
                @isset($tag['title']) title="{{ $tag['title'] }}" @endisset>{{ $tag['text'] }}</span>
        @endforeach

        @if (!empty($hidden))
            <span class="ts mini basic label uq-label uq-label-more"
                title="{{ implode('、', array_column($hidden, 'text')) }}">+{{ count($hidden) }}</span>
        @endif
    </div>
@endif
