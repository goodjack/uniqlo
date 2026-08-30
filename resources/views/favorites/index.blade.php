@extends('layouts.master')

@section('title', '我的收藏')

@section('metadata')
    {{-- 內容只存在使用者的瀏覽器裡，沒有東西可以給搜尋引擎索引 --}}
    <meta name="robots" content="noindex, nofollow" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="heart faded icon"></i>
        <span class="header">我的收藏</span>
        <span class="description" id="favorites-summary">收藏只存在這個瀏覽器，換裝置看不到</span>
    </div>

    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container">
            <div class="ts relaxed divided items" id="favorites-cards"></div>

            <div class="ts center aligned basic segment" id="favorites-empty" hidden>
                <div class="ts icon header">
                    <i class="heart outline faded icon"></i>
                    <div class="content">
                        還沒有收藏任何商品
                        <div class="sub header">在商品頁按「追蹤降價」就會出現在這裡</div>
                    </div>
                </div>
            </div>

            <div class="ts center aligned basic segment" id="favorites-loading">
                <div class="ts active text loader">載入中</div>
            </div>
        </div>
    </div>
@endsection

@section('javascript')
    <script src="{{ asset('js/favorites.js') }}"></script>
    <script>
        UqFavorites.renderPage({
            cardsUrl: '{{ route('favorites.cards') }}',
            csrfToken: '{{ csrf_token() }}',
        });
    </script>
@endsection
