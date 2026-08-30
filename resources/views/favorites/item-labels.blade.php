{{-- 收藏清單每一列的標籤與操作：哪一家、它現在的狀態、以及移除。 --}}
<div class="extra">
    <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif label">
        {{ $hmallProduct->brand }}
    </div>
    @include('hmall-products.partials.card-labels', ['hmallProduct' => $hmallProduct])
</div>
<div class="extra">
    {{-- 整列是一個 <a>，按鈕不能用 <button>（巢狀互動元素是無效的 HTML）， --}}
    {{-- 所以用帶 role 的 span，click 時擋掉連結的預設行為。 --}}
    <span class="ts mini basic button" data-favorite-remove role="button" tabindex="0"
        data-brand="{{ $hmallProduct->brand }}" data-code="{{ $hmallProduct->product_code }}">
        <i class="times icon"></i>移除收藏
    </span>
</div>
