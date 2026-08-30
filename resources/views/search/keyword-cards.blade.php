{{-- 關鍵字搜尋的結果。只含現行商品，舊軌 Product 已凍結不納入搜尋。 --}}
<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        @if ($hmallProducts->isNotEmpty())
            <div class="ts doubling link cards four">
                @each('hmall-products.card', $hmallProducts, 'hmallProduct')
            </div>

            @include('partials.pagination', ['paginator' => $hmallProducts])
        @else
            <div class="ts center aligned basic segment">
                <div class="ts icon header">
                    <i class="search faded icon"></i>
                    <div class="content">
                        找不到符合的商品
                        <div class="sub header">試試看少打幾個字，或換個說法</div>
                    </div>
                </div>
            </div>
        @endif

        <div class="ts center aligned basic segment">
            {{-- 參數名是 Google Programmable Search 的標準 q，CSE 元件會自己讀它帶入搜尋框 --}}
            <a class="ts basic button" href="{{ route('search.google-cse', ['q' => $query]) }}">
                <i class="google icon"></i>改用 Google 搜尋「{{ $query }}」
            </a>
        </div>
    </div>
</div>
