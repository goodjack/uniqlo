@php
    /**
     * 商品標籤篩選的 chip 列。清單頁與分類頁共用同一份定義與外觀。
     *
     * 用 GET form 而不是 JavaScript：網址帶得走、能分享、能用上一頁，
     * 沒有 JavaScript 也能操作。這個站沒有前端建置流程，也不該為了篩選引入。
     *
     * 展開與收合同樣是純 CSS：下面那個藏起來的勾選框配 partials/tag-filter-button
     * 的 label，兩個 partial 要成對出現。網址帶 tags[] 時預設就是展開的。
     *
     * 這個 partial 是工具列的兄弟節點、不是它的子節點：面板有自己的底色與圓角，
     * 掛在工具列裡會被那條 border-bottom 切在中間。
     */
    $tagOptions = \App\Enums\ProductTag::cases();
    $selectedTags = collect(\App\Enums\ProductTag::fromValues((array) request('tags', [])))
        ->map->value
        ->all();

    /*
     * 切換篩選不該丟掉品牌、排序與搜尋關鍵字，它們是各自獨立的軸。只帶這三個、
     * 而且只在它們是字串時帶：先前用 request()->except() 把所有其他參數原封搬進
     * hidden input，遇到 ?ref[]=x 這種陣列就是把 array 丟給 Blade 轉字串，整頁 500。
     */
    $otherParams = array_filter(request()->only(['brand', 'sort', 'q']), 'is_string');
@endphp

{{-- 勾選框要跟 chip 列同一層、而且排在它前面，CSS 的 ~ 才選得到 --}}
<input type="checkbox" id="uq-tag-filter" class="uq-filter-switch" @checked(! empty($selectedTags))>

<form method="GET" action="{{ url()->current() }}" class="uq-chip-row">
    @foreach ($otherParams as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach

    <div class="uq-chips">
        @foreach ($tagOptions as $tag)
            <label class="uq-chip">
                <input type="checkbox" name="tags[]" value="{{ $tag->value }}"
                    @checked(in_array($tag->value, $selectedTags, true))>
                {{ $tag->label() }}
            </label>
        @endforeach
    </div>

    <div class="uq-chip-footer">
        {{-- 多選是聯集不是交集，這句話是使用者勾第二個之前唯一看得到的說明 --}}
        <span class="uq-chip-hint">符合任一條件即顯示</span>

        <div class="uq-chip-actions">
            @if (! empty($selectedTags))
                <a class="uq-chip-clear"
                    href="{{ url()->current() }}{{ $otherParams ? '?' . http_build_query($otherParams) : '' }}">清除</a>
            @endif
            <button class="uq-chip-apply" type="submit">套用</button>
        </div>
    </div>
</form>
