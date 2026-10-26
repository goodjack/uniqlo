<form class="ts fluid action input" action="{{ route('search.index') }}">
    {{-- 帶回這次查的字，跟 SearchController 一樣只認路徑與網址參數。is_string：每一頁都有
         這個框，?query[]=x 會讓全站（含錯誤頁）500 --}}
    @php
        $searchedQuery = request()->route('query') ?? request()->query('query');
    @endphp
    {{-- 多了條碼圖示後手機上放不下完整提示，barcode-scan.js 在窄螢幕換成短的 --}}
    <input name="query" inputmode="search" placeholder="輸入關鍵字或編號..." required
        @if (config('barcode.enabled')) data-placeholder-narrow="關鍵字或編號" @endif
        value="{{ is_string($searchedQuery) ? $searchedQuery : '' }}">
    @if (config('barcode.enabled'))
        <button class="uq-scan-trigger" type="button" data-barcode-scan aria-label="掃吊牌條碼"
            aria-haspopup="dialog">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 7.5 3 3 7.5 3" />
                    <polyline points="16.5 3 21 3 21 7.5" />
                    <polyline points="21 16.5 21 21 16.5 21" />
                    <polyline points="7.5 21 3 21 3 16.5" />
                </g>
                <g fill="currentColor">
                    <rect x="6" y="7" width="1.5" height="10" />
                    <rect x="9" y="7" width="1.5" height="10" />
                    <rect x="12" y="7" width="3" height="10" />
                    <rect x="16.5" y="7" width="1.5" height="10" />
                </g>
            </svg>
        </button>
    @endif
    <button class="ts basic icon button" type="submit" aria-label="Search">
        <i class="search icon"></i>
    </button>
</form>
