{{--
    麵包屑。除了首頁以外每一頁都有，位置固定在 container 內的第一行。

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
                <i class="right chevron icon divider" aria-hidden="true"></i>
            @endunless
        @endforeach
    </nav>
@endif
