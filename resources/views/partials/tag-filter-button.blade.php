{{-- 篩選的展開鈕，是 partials/tag-filter 裡那個勾選框的 label --}}
@php
    $selectedTagCount = count(\App\Enums\ProductTag::fromValues((array) request('tags', [])));
@endphp

<label class="ts basic compact button uq-filter-toggle" for="uq-tag-filter">
    <i class="filter icon" aria-hidden="true"></i>篩選
    @if ($selectedTagCount)
        <div class="ts tiny circular label">{{ $selectedTagCount }}</div>
    @endif
</label>
