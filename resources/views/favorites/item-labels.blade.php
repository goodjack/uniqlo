{{-- 收藏清單每一列的標籤：哪一家，以及它現在的狀態。 --}}
<div class="extra">
    <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif label">
        {{ $hmallProduct->brand }}
    </div>
    @include('hmall-products.partials.card-labels', ['hmallProduct' => $hmallProduct])
</div>
