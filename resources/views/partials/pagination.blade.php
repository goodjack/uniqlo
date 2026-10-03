{{--
    分頁。Laravel 內建的分頁樣板是 Bootstrap 與 Tailwind 版，這裡用 Tocas 寫。

    paginator  LengthAwarePaginator；要保留 query string 由呼叫端 withQueryString()
    bare       true 時不包外層 segment，給已經自帶外框的頁面用
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
            @if (is_string($element))
                <a class="ts icon disabled button" aria-disabled="true">{{ $element }}</a>
            @endif

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
