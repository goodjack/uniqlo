<form class="ts fluid action input" action="{{ route('search.index') }}">
    {{-- 帶回這次查的字，使用者才能改字而不是重打。/search/{query} 走路由參數、
         /search?query= 走查詢字串，兩種入口都要顧到。 --}}
    <input name="query" inputmode="search" placeholder="輸入關鍵字或編號..." required
        value="{{ request()->route('query') ?? request('query') }}">
    <button class="ts basic icon button" type="submit" aria-label="Search">
        <i class="search icon"></i>
    </button>
</form>
