{{--
    分頁。Laravel 內建的分頁樣板是 Bootstrap 與 Tailwind 版，這裡用 Tocas 寫。

    paginator  LengthAwarePaginator；要保留 query string 由呼叫端 withQueryString()
    bare       true 時不包外層 segment，給已經自帶外框的頁面用
--}}
@php
    $bare = $bare ?? false;

    // 第 1 頁連回不帶頁碼的網址：canonical 就是它，帶 page=1 只會讓搜尋引擎多抓一個重複網址
    $pageUrl = function (int $page) use ($paginator) {
        if ($page !== 1) {
            return $paginator->url($page);
        }

        parse_str(parse_url($paginator->url(1), PHP_URL_QUERY) ?? '', $query);
        unset($query[$paginator->getPageName()]);

        return $paginator->path().($query ? '?'.\Illuminate\Support\Arr::query($query) : '');
    };
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
            <a class="ts icon button" href="{{ $pageUrl($paginator->currentPage() - 1) }}" rel="prev"
                aria-label="@lang('pagination.previous')">
                <i class="left chevron icon"></i>
            </a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <a class="ts icon disabled button" aria-disabled="true">{{ $element }}</a>
            @endif

            @if (is_array($element))
                @foreach (array_keys($element) as $page)
                    @if ($page == $paginator->currentPage())
                        <a class="ts icon active button" href="{{ $pageUrl($page) }}" aria-current="page">{{ $page }}</a>
                    @else
                        <a class="ts icon button" href="{{ $pageUrl($page) }}">{{ $page }}</a>
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
