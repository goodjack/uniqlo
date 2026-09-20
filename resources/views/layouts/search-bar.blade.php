<form class="ts fluid action input" action="{{ route('search.index') }}">
    {{-- 帶回這次查的字，使用者才能改字而不是重打。/search/{query} 走路由參數、
         /search?query= 走查詢字串，兩種入口都要顧到。這個 partial 被 nav 引入、
         出現在每一頁，?query[]=x 這種陣列輸入不能直接丟進 htmlspecialchars()，
         不是字串就當作沒帶查詢字，不然全站每一頁（含 404 錯誤頁）都會 500。 --}}
    <input name="query" inputmode="search" placeholder="輸入關鍵字或編號..." required
        value="{{ is_string(request()->route('query')) ? request()->route('query') : (is_string(request('query')) ? request('query') : '') }}">
    <button class="ts basic icon button" type="submit" aria-label="Search">
        <i class="search icon"></i>
    </button>
</form>
