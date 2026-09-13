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
        {{-- 一件都沒有的時候整條工具列不出現，favorites.js 有東西可以列才打開 --}}
        <x-toolbar id="favorites-toolbar" hidden>
            <x-slot:end>
                <button type="button" class="ts basic compact button" data-favorites-clear>清空收藏</button>
            </x-slot:end>
        </x-toolbar>

        <div class="ts relaxed divided items" id="favorites-cards"></div>

        {{--
            三種狀態長得不一樣，使用者才知道自己該做什麼。這一種不傳 icon：
            上面第 12 行的頁首紅愛心已經講完「這是收藏頁」，這裡再放一顆
            heart outline 只是重複，而且比頁首那顆更大更深、反而搶戲。
        --}}
        @include('partials.empty-state', [
            'attributes' => 'id="favorites-empty" hidden',
            'title' => '還沒有收藏任何商品',
            'hint' => '在商品頁按「收藏」就會出現在這裡',
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
