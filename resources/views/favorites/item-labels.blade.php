{{-- favorites-labels 讓 app.css 把品牌角標與狀態標籤併進同一個可換行的區域 --}}
<div class="extra favorites-labels">
    <div class="ts mini @if ($hmallProduct->brand === 'GU') info @else negative @endif label">
        {{ $hmallProduct->brand }}
    </div>
    @include('hmall-products.partials.card-labels', ['hmallProduct' => $hmallProduct])
</div>
