@extends('layouts.master')

@php
    use App\Support\Breadcrumb;

    $crumbs = [Breadcrumb::home(), Breadcrumb::link('favorites')];
@endphp

@section('title', '我的收藏')

@section('metadata')
    {{-- 內容只存在使用者的瀏覽器裡，沒有東西可以給搜尋引擎索引 --}}
    <meta name="robots" content="noindex, nofollow" />
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="heart faded icon"></i>
        <span class="header">收藏</span>
        <span class="description" id="favorites-summary">只存在這個瀏覽器，換裝置看不到</span>
    </div>

    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container">
            @include('partials.breadcrumb', ['crumbs' => $crumbs])

            {{-- 一件都沒有的時候整條工具列不出現，favorites.js 有東西可以列才打開 --}}
            <x-toolbar id="favorites-toolbar" hidden>
                <x-slot:end>
                    <button type="button" class="uq-control" data-favorites-clear>全部清除</button>
                </x-slot:end>
            </x-toolbar>

            <div class="ts relaxed divided items" id="favorites-cards"></div>

            {{-- 三種狀態長得不一樣，使用者才知道自己該做什麼 --}}
            @include('partials.empty-state', [
                'attributes' => 'id="favorites-empty" hidden',
                'icon' => 'heart outline faded',
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
    </div>
@endsection

@section('javascript')
    <script>
        UqFavorites.renderPage({
            cardsUrl: '{{ route('favorites.cards') }}',
            csrfToken: '{{ csrf_token() }}',
        });
    </script>
@endsection
