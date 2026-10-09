@extends('layouts.master')

@section('title', '我的收藏')

@section('metadata')
    <meta name="robots" content="noindex, nofollow" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="heart negative icon"></i>
        <span class="header">收藏</span>
        <span class="description" id="favorites-summary">只存在這個瀏覽器，換裝置看不到</span>
    </div>

    <div class="ts container">
        {{-- 工具列與篩選由 favorites.js 在有東西可以列時才打開 --}}
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

        {{-- 四種空狀態分開講：沒收藏、篩選後沒有、都已下架、載入失敗，使用者才知道收藏沒有不見 --}}
        @include('partials.empty-state', [
            'attributes' => 'id="favorites-empty" hidden',
            'title' => '還沒有收藏任何商品',
            'hint' => '在商品頁按「收藏」就會出現在這裡',
        ])

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

        {{-- 載入失敗也要能清空：一載入就錯的資料只靠「重新載入」沒有出路 --}}
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
            cardsUrl: @js(route('favorites.cards')),
        });
    </script>
@endsection
