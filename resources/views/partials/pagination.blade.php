{{-- Tocas 風格的簡易分頁。Laravel 內建的分頁樣板都是 Bootstrap 或 Tailwind，這個專案兩者都沒有。 --}}
@if ($paginator->hasPages())
    <div class="ts center aligned basic segment">
        <div class="ts small buttons">
            @if ($paginator->onFirstPage())
                <div class="ts disabled button">上一頁</div>
            @else
                <a class="ts button" href="{{ $paginator->previousPageUrl() }}" rel="prev">上一頁</a>
            @endif

            <div class="ts basic button">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</div>

            @if ($paginator->hasMorePages())
                <a class="ts button" href="{{ $paginator->nextPageUrl() }}" rel="next">下一頁</a>
            @else
                <div class="ts disabled button">下一頁</div>
            @endif
        </div>
    </div>
@endif
