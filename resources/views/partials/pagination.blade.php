{{--
    分頁：上一頁／頁碼／下一頁。取代原本各自維護的三顆 pill 版與 style-hints
    專用版，兩邊現在共用同一份 markup，也共用同一顆
    Illuminate\Pagination\UrlWindow 算出來的頁碼視窗。

    paginator  LengthAwarePaginator。要保留 query string 的話呼叫端在組
               paginator 時自己呼叫 withQueryString()，這裡只讀網址方法。
    bare       true 時只吐頁碼按鈕列，不包自己的置中 segment——style-hints
               頁本來就有一層帶底色的 fluid segment 負責外框，兩層 segment
               疊起來會變成雙重留白，那頁自己包。分類頁與搜尋頁不用管這個
               參數，維持預設的 false。
--}}
@php
    $bare = $bare ?? false;
@endphp

@if ($paginator->hasPages())
    @php
        $window = \Illuminate\Pagination\UrlWindow::make($paginator);

        $elements = array_filter([
            $window['first'],
            is_array($window['slider']) ? '...' : null,
            $window['slider'],
            is_array($window['last']) ? '...' : null,
            $window['last'],
        ]);
    @endphp

    @unless ($bare)
        <div class="ts center aligned basic segment uq-pagination">
    @endunless

    <div class="ts small buttons">
        @if ($paginator->onFirstPage())
            <a class="ts icon disabled button" aria-disabled="true" aria-label="@lang('pagination.previous')">
                <i class="left chevron icon"></i>
            </a>
        @else
            <a class="ts icon button" href="{{ $paginator->previousPageUrl() }}" rel="prev"
                aria-label="@lang('pagination.previous')">
                <i class="left chevron icon"></i>
            </a>
        @endif

        @foreach ($elements as $element)
            {{-- 「…」省略號 --}}
            @if (is_string($element))
                <a class="ts icon disabled button" aria-disabled="true">{{ $element }}</a>
            @endif

            {{-- 頁碼陣列 --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <a class="ts icon active button" href="{{ $url }}" aria-current="page">{{ $page }}</a>
                    @else
                        <a class="ts icon button" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="ts icon button" href="{{ $paginator->nextPageUrl() }}" rel="next"
                aria-label="@lang('pagination.next')">
                <i class="right chevron icon"></i>
            </a>
        @else
            <a class="ts icon disabled button" aria-disabled="true" aria-label="@lang('pagination.next')">
                <i class="right chevron icon"></i>
            </a>
        @endif
    </div>

    @unless ($bare)
        </div>
    @endunless
@endif
