@php
    /**
     * 商品標籤篩選，清單頁與分類頁共用。用 GET 表單，網址能分享、沒有
     * JavaScript 也能用。展開與收合是純 CSS：下面藏起來的勾選框配
     * partials/tag-filter-button 的 label，兩個 partial 要成對出現。
     */
    $tagOptions = \App\Enums\ProductTag::cases();
    $selectedTags = (array) request('tags', []);

    // 切換篩選時保留品牌、排序與關鍵字
    $otherParams = request()->only(['brand', 'sort', 'q']);
    $urlWithTags = function (array $tags) use ($otherParams) {
        $query = http_build_query($otherParams + ($tags ? ['tags' => array_values($tags)] : []));

        return url()->current().($query !== '' ? '?'.$query : '');
    };
@endphp

{{-- 勾選框要跟下面兩塊同一層而且排在前面，CSS 的 ~ 才選得到 --}}
<input type="checkbox" id="uq-tag-filter" class="uq-filter-switch">

@if (! empty($selectedTags))
    <div class="uq-applied-tags" data-keeps-q data-shows-busy>
        @foreach ($tagOptions as $tag)
            @if (in_array($tag->value, $selectedTags, true))
                <a class="uq-chip" href="{{ $urlWithTags(array_diff($selectedTags, [$tag->value])) }}"
                    aria-label="拿掉「{{ $tag->label() }}」">{{ $tag->label() }}<i class="close icon" aria-hidden="true"></i></a>
            @endif
        @endforeach
        <a class="uq-text-action" href="{{ $urlWithTags([]) }}">全部清除</a>
    </div>
@endif

<form method="GET" action="{{ url()->current() }}" class="uq-chip-row" data-keeps-q data-shows-busy>
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

    <div class="uq-chip-actions">
        @if (! empty($selectedTags))
            <a class="uq-text-action" href="{{ $urlWithTags([]) }}">清除條件</a>
        @endif
        <button class="ts inverted button" type="submit">套用</button>
    </div>
</form>
