<form class="ts fluid action input" action="{{ route('search.index') }}">
    {{-- 帶回這次查的字。is_string：每一頁都有這個框，?query[]=x 會讓全站（含錯誤頁）500 --}}
    <input name="query" inputmode="search" placeholder="輸入關鍵字或編號..." required
        value="{{ is_string(request()->route('query')) ? request()->route('query') : (is_string(request('query')) ? request('query') : '') }}">
    <button class="ts basic icon button" type="submit" aria-label="Search">
        <i class="search icon"></i>
    </button>
</form>
