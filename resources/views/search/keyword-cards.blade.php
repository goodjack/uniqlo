{{-- 關鍵字搜尋的結果。只含現行商品，舊軌 Product 已凍結不納入搜尋。 --}}
<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        @if ($hmallProducts->isNotEmpty())
            <div class="ts doubling link cards four">
                @each('hmall-products.card', $hmallProducts, 'hmallProduct')
            </div>

            @include('partials.pagination', ['paginator' => $hmallProducts])
        @else
            {{-- 搜尋的空狀態文案跟清單頁不同：這裡要改的是關鍵字，不是勾選的條件 --}}
            @include('partials.empty-state', [
                'icon' => 'search faded',
                'title' => '找不到符合的商品',
                'hint' => '試試看少打幾個字，或換個說法',
            ])
        @endif

        <div class="ts center aligned basic segment">
            {{-- 參數名是 Google Programmable Search 的標準 q，CSE 元件會自己讀它帶入搜尋框 --}}
            <a class="ts basic button" href="{{ route('search.google-cse', ['q' => $query]) }}">
                <i class="google icon"></i>改用 Google 搜尋「{{ $query }}」
            </a>
        </div>
    </div>
</div>
