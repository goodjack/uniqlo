@php
    /**
     * 商品標籤篩選，清單頁與分類頁共用。用 GET 表單，網址能分享、沒有
     * JavaScript 也能用。展開與收合是純 CSS：下面藏起來的勾選框配
     * partials/tag-filter-button 的 label，兩個 partial 要成對出現。
     */
    $tagOptions = \App\Enums\ProductTag::cases();
    $selectedTags = collect(\App\Enums\ProductTag::fromValues((array) request('tags', [])))
        ->map->value
        ->all();

    // 保留品牌、排序與關鍵字；只收字串，網址塞陣列（?q[]=x）時不能讓 Blade 轉字串而 500
    $otherParams = array_filter(request()->only(['brand', 'sort', 'q']), 'is_string');
@endphp

{{-- 勾選框要跟 chip 列同一層而且排在前面，CSS 的 ~ 才選得到 --}}
<input type="checkbox" id="uq-tag-filter" class="uq-filter-switch" @checked(! empty($selectedTags))>

<form method="GET" action="{{ url()->current() }}" class="uq-chip-row" data-keeps-q>
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

    <p>符合任一條件即顯示</p>

    <div class="ts compact buttons">
        @if (! empty($selectedTags))
            <a class="ts basic button"
                href="{{ url()->current() }}{{ $otherParams ? '?' . http_build_query($otherParams) : '' }}">清除</a>
        @endif
        <button class="ts button" type="submit">套用</button>
    </div>
</form>
