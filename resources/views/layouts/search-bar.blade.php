<form class="ts fluid action input" action="{{ route('search.index') }}">
    {{-- 帶回這次查的字，跟 SearchController 一樣只認路徑與網址參數。is_string：每一頁都有
         這個框，?query[]=x 會讓全站（含錯誤頁）500 --}}
    @php
        $searchedQuery = request()->route('query') ?? request()->query('query');
    @endphp
    <input name="query" inputmode="search" placeholder="輸入關鍵字或編號..." required
        value="{{ is_string($searchedQuery) ? $searchedQuery : '' }}">
    <button class="ts basic icon button" type="submit" aria-label="Search">
        <i class="search icon"></i>
    </button>
</form>
