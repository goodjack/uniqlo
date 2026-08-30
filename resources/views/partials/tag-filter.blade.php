@php
    /**
     * 商品標籤篩選。清單頁與分類頁共用同一份定義與外觀。
     *
     * 用 GET form 而不是 JavaScript：網址帶得走、能分享、能用上一頁，
     * 沒有 JavaScript 也能操作。這個站沒有前端建置流程，也不該為了篩選引入。
     */
    $tagOptions = collect(\App\Enums\ProductTag::cases())
        ->mapWithKeys(fn($tag) => [$tag->value => $tag->label()]);

    $selectedTags = array_intersect((array) request('tags', []), $tagOptions->keys()->all());
    // 切換篩選不該丟掉品牌與排序，它們是各自獨立的軸
    $otherParams = request()->except(['tags', 'page']);
@endphp

<form method="GET" action="{{ url()->current() }}" class="tag-filter">
    @foreach ($otherParams as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach

    <details @if (!empty($selectedTags)) open @endif>
        <summary>
            篩選商品
            @if (count($selectedTags))
                <span class="ts mini circular label">已選 {{ count($selectedTags) }}</span>
            @endif
        </summary>

        <div class="tag-filter-options">
            @foreach ($tagOptions as $tag => $label)
                <label class="ts basic label">
                    <input type="checkbox" name="tags[]" value="{{ $tag }}"
                        @checked(in_array($tag, $selectedTags, true))>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="tag-filter-actions">
            <span class="ts tiny disabled text">符合任一條件即顯示</span>
            <button class="ts small primary button" type="submit">套用篩選</button>
            @if (!empty($selectedTags))
                <a class="ts small basic button"
                    href="{{ url()->current() }}{{ $otherParams ? '?' . http_build_query($otherParams) : '' }}">清除</a>
            @endif
        </div>
    </details>
</form>
