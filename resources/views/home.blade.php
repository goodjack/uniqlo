@extends('layouts.master')

@section('title', '首頁')

@section('css')
    @include('partials.list-type-icon-styles')
    <style>
        .home-section-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
        }

        /*
         * 橫向捲動的卡片列。刻意不掛 Tocas 的 doubling／six，
         * 那組規則的權重比這裡高、會蓋掉下面的寬度設定。
         */
        .home-product-row.ts.cards {
            flex-wrap: nowrap;
            overflow-x: auto;
            overscroll-behavior-x: contain;
            scroll-snap-type: x proximity;
            scroll-padding-left: 0.5em;
            /* Tocas 給 .ts.cards 的負 margin 在可捲動容器裡會裁掉第一張卡片 */
            margin-left: 0;
            margin-right: 0;
            padding-bottom: 0.5rem;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        .home-product-row.ts.cards>.card {
            flex: 0 0 auto;
            scroll-snap-align: start;
            /* 手機一次露出兩張多一點，半截的第三張就是「可以左右滑」的提示 */
            width: 42%;
        }

        @media (min-width: 768px) {
            .home-product-row.ts.cards>.card {
                width: 26%;
            }
        }

        @media (min-width: 992px) {
            .home-product-row.ts.cards>.card {
                width: 21%;
            }
        }

        @media (min-width: 1200px) {
            .home-product-row.ts.cards>.card {
                width: 16.2%;
            }
        }
    </style>
@endsection

@section('content')
    <div class="ts very padded horizontally fitted attached fluid segment">
        <div class="ts very narrow container">
            <div class="ts hidden divider"></div>
            <div class="ts hidden divider"></div>
            <div class="ts hidden divider"></div>
            <h1 class="ts center aligned header">
                <i class="big fitted negative clone icon"></i>
                &nbsp;
                UQ 搜尋
            </h1>
            <div class="ts hidden divider"></div>
            <div class="ts hidden divider"></div>
            <form class="ts big form" action="{{ route('search.index') }}">
                <input name="query" class="ts fluid input" inputmode="search" placeholder="輸入關鍵字，或 UNIQLO/GU 商品編號">
                <div class="ts hidden divider"></div>
                <center>
                    <button class="ts button" type="submit">搜尋</button>
                </center>
            </form>
            <div class="ts hidden divider"></div>
        </div>
    </div>

    @foreach ($sections as $section)
        @include('home.partials.product-row', ['section' => $section])
    @endforeach
@endsection
