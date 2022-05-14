<form class="ts fluid action input" action="{{ route('search.index') }}">
    <input name="query" inputmode="search" placeholder="輸入關鍵字或編號..." required>
    <button class="ts basic icon button" type="submit" aria-label="Search">
        <i class="search icon"></i>
    </button>
</form>
