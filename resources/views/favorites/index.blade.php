@extends('layouts.master')

@section('title', '我的收藏')

@section('metadata')
    {{-- 內容只存在使用者的瀏覽器裡，沒有東西可以給搜尋引擎索引 --}}
    <meta name="robots" content="noindex, nofollow" />
@endsection

@section('content')
    <div class="ts fluid slate">
        {{-- 這頁的主題就是收藏，愛心不用灰階淡化，改用 Tocas 原生的
             negative（品牌紅 #CE5F58）實心愛心；跟第 30 行「還沒有收藏」
             那顆維持灰色的空心愛心是兩種狀態，不要改成一樣。 --}}
        <i class="heart negative icon"></i>
        <span class="header">收藏</span>
        <span class="description" id="favorites-summary">只存在這個瀏覽器，換裝置看不到</span>
    </div>

    <div class="ts container">
        {{--
            一件都沒有的時候整條工具列不出現，favorites.js 有東西可以列才打開。

            左側是「只看優惠中」篩選：預設關閉、不記住狀態，重新整理一律回到
            關閉。這裡沿用清單頁篩選面板同一套 .uq-chip 圓角樣式（底層真的
            checkbox），但不是同一顆——這裡是純前端的即時篩選，沒有 GET 表單、
            沒有「套用」鈕，勾了立刻生效。favorites-filter-control 整顆只在
            有卡片可以篩的時候出現（0 張卡片時 favorites.js 會把它藏起來），
            商品都下架、或還沒收藏任何商品的狀態下不該多一顆篩選鈕。
        --}}
        <x-toolbar id="favorites-toolbar" hidden>
            <x-slot:start>
                <span id="favorites-filter-control" class="favorites-filter-control" hidden>
                    <label class="uq-chip">
                        <input type="checkbox" id="favorites-offer-filter">
                        只看優惠中
                    </label>
                    <span id="favorites-filter-summary" class="favorites-filter-summary"></span>
                </span>
            </x-slot:start>
            <x-slot:end>
                <button type="button" class="ts basic compact button" data-favorites-clear>清空收藏</button>
            </x-slot:end>
        </x-toolbar>

        <div class="ts relaxed divided items" id="favorites-cards"></div>

        {{--
            四種狀態長得不一樣，使用者才知道自己該做什麼。這一種不傳 icon：
            上面第 12 行的頁首紅愛心已經講完「這是收藏頁」，這裡再放一顆
            heart outline 只是重複，而且比頁首那顆更大更深、反而搶戲。
        --}}
        @include('partials.empty-state', [
            'attributes' => 'id="favorites-empty" hidden',
            'title' => '還沒有收藏任何商品',
            'hint' => '在商品頁按「收藏」就會出現在這裡',
        ])

        {{--
            開著「只看優惠中」但一件都不符合時的專用空狀態，不能跟上面那個
            「還沒有收藏任何商品」共用——使用者其實有收藏，只是這次篩選沒有
            結果，兩者是完全不同的狀況。「顯示全部」直接關閉篩選、不用使用者
            自己去找那顆勾選框。
        --}}
        @include('partials.empty-state', [
            'attributes' => 'id="favorites-filter-empty" hidden',
            'icon' => 'filter faded',
            'title' => '目前沒有優惠中的收藏',
            'hint' => '其他收藏商品都還在，可以看全部或再等等',
            'slot' => '<button class="ts basic button" id="favorites-filter-show-all">顯示全部</button>',
        ])

        @include('partials.empty-state', [
            'attributes' => 'id="favorites-gone" hidden',
            'icon' => 'archive faded',
            'title' => '收藏的商品都已經下架了',
            'hint' => '官網已經買不到這些商品，可以把它們從收藏移除',
            'slot' => '<button class="ts basic button" data-favorites-clear>清空收藏</button>',
        ])

        {{-- 載入失敗也要能清空：只給「重新載入」的話，一按就錯的狀態沒有出路 --}}
        @include('partials.empty-state', [
            'attributes' => 'id="favorites-error" hidden',
            'icon' => 'warning circle faded',
            'title' => '暫時載入不到收藏',
            'hint' => '收藏清單還在你的瀏覽器裡，沒有遺失',
            'slot' =>
                '<button class="ts basic button" id="favorites-retry">重新載入</button>' .
                ' <button class="ts basic button" data-favorites-clear>清空收藏</button>',
        ])

        <div class="ts center aligned basic segment" id="favorites-loading">
        <div class="ts active text loader">載入中</div>
        </div>
    </div>

    @include('favorites.snackbar')
    @include('favorites.clear-modal')
@endsection

@section('javascript')
    <script>
        UqFavorites.renderPage({
            cardsUrl: '{{ route('favorites.cards') }}',
            csrfToken: '{{ csrf_token() }}',
        });
    </script>
@endsection
