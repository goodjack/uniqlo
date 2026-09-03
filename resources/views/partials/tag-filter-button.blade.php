{{--
    篩選的觸發鈕，放進工具列右側。

    展開狀態是 partials/tag-filter 裡那個藏起來的勾選框，這裡只是它的 label，
    所以兩個 partial 一定要成對出現、而且勾選框要排在 chip 列前面。
--}}
@php
    $selectedTagCount = count(\App\Enums\ProductTag::fromValues((array) request('tags', [])));
@endphp

<label class="ts small basic button uq-filter-toggle" for="uq-tag-filter">
    {{-- icon 純裝飾，可及名稱是「篩選」兩個字。Tocas 的 icon 是 icon font 的 --}}
    {{-- ::before 內容，不標 aria-hidden 會被念成一串沒有意義的字元 --}}
    <i class="filter icon" aria-hidden="true"></i>篩選
    @if ($selectedTagCount)
        <span class="ts mini circular label">{{ $selectedTagCount }}</span>
    @endif
</label>
