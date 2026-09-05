{{--
    麵包屑。只有分類頁與商品頁有：那兩頁是分類樹上的一個位置，往上一層是有意義的
    去處。清單、搜尋、收藏、分類總覽都是從導覽列直接進來的單層頁面，加一條「首頁 ›
    自己」只是佔一行。

    crumbs  陣列，每一筆是 ['label' => string, 'url' => ?string]。
            url 是 null 的那幾層只當文字（例如導覽的分組名、沒有自己頁面的頂層分類）。
            最後一筆一律是當頁：不做連結、標 aria-current="page"。
--}}
@if (!empty($crumbs))
    <nav class="ts small breadcrumb uq-breadcrumb" aria-label="麵包屑">
        @foreach ($crumbs as $crumb)
            @if ($loop->last)
                <div class="active section" aria-current="page">{{ $crumb['label'] }}</div>
            @elseif (!empty($crumb['url']))
                <a class="section" href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
            @else
                <div class="section">{{ $crumb['label'] }}</div>
            @endif

            @unless ($loop->last)
                <i class="angle right icon divider" aria-hidden="true"></i>
            @endunless
        @endforeach
    </nav>
@endif
