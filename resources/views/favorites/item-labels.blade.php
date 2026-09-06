{{-- 收藏清單每一列的標籤與操作：哪一家、它現在的狀態、以及移除。 --}}
<div class="extra">
    <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif label">
        {{ $hmallProduct->brand }}
    </div>
    @include('hmall-products.partials.card-labels', ['hmallProduct' => $hmallProduct])
</div>
<div class="extra">
    {{-- 整列不再是一個 <a>，這裡就能用真的 <button>：焦點、Enter 與空白鍵都由瀏覽器處理 --}}
    <button type="button" class="ts mini basic button" data-favorite-remove
        data-brand="{{ $hmallProduct->brand }}" data-code="{{ $hmallProduct->product_code }}">
        <i class="close icon"></i>移除收藏
    </button>
</div>
