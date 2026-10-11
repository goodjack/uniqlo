{{-- 只搜現行商品，舊系統的 Product 已凍結不納入 --}}
@if ($hmallProducts->isNotEmpty())
    <div class="ts doubling cards four uq-product-cards">
        @each('hmall-products.card', $hmallProducts, 'hmallProduct')
    </div>

    @include('partials.pagination', ['paginator' => $hmallProducts])
@else
    @include('partials.empty-state', [
        'icon' => 'search faded',
        'title' => '找不到符合的商品',
        'hint' => '試試看少打幾個字，或換個說法',
    ])
@endif

<div class="ts center aligned basic segment">
    {{-- 參數名要跟 Google 搜尋引擎後台設定的一致（query），不是官方文件說的預設 q --}}
    <a class="ts basic button" href="{{ route('search.google-cse', ['query' => $query]) }}">
        <i class="google icon"></i>改用 Google 搜尋「{{ $query }}」
    </a>
</div>
