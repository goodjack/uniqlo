{{--
    麵包屑，只給分類樹上的頁面（分類頁、商品頁）用；單層頁面加了只是佔一行。

    crumbs  [['label', 'url'?], ...]；沒有 url 的只當文字，最後一筆是當頁
--}}
@if (!empty($crumbs))
    {{-- 麵包屑自己沒有 margin，不包一層 segment 就貼著導覽列下緣 --}}
    <div class="ts basic horizontally fitted segment">
        <nav class="ts breadcrumb" aria-label="麵包屑">
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
    </div>
@endif
