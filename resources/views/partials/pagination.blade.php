{{--
    分頁。Laravel 內建的分頁樣板都是 Bootstrap 或 Tailwind，這個專案兩者都沒有。

    刻意不掛 Tocas 的 .ts.buttons：那組規則的排版與 hover 配色是六到十五個條件疊
    出來的權重，要蓋得動得寫一長串 :not()。三顆各自用工具列那個 .uq-control 就好，
    全站的控制項本來就該長成同一個樣子。
--}}
@if ($paginator->hasPages())
    <div class="ts center aligned basic segment uq-pagination">
        @if ($paginator->onFirstPage())
            <span class="uq-control uq-pagination-disabled">上一頁</span>
        @else
            <a class="uq-control" href="{{ $paginator->previousPageUrl() }}" rel="prev">上一頁</a>
        @endif

        <span class="uq-control uq-pagination-current">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="uq-control" href="{{ $paginator->nextPageUrl() }}" rel="next">下一頁</a>
        @else
            <span class="uq-control uq-pagination-disabled">下一頁</span>
        @endif
    </div>
@endif
