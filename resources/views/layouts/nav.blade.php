<!-- 頂部固定選單 -->
<div class="ts top fixed small link menu">
    <div class="ts container">
        <a href="{{ route('home') }}" class="header item">
            <i class="large negative clone icon"></i>UQ 搜尋
        </a>
        <div class="stretched item">
            @include('layouts.search-bar')
        </div>
        <div class="large device only stretched item">
        </div>
        <div class="tablet or large device only right menu">
            <a href="{{ route('home') }}" class="item" aria-label="home">首頁</a>
            <a href="{{ route('products.limited-offers') }}" class="item" aria-label="limited-offers">期間限定</a>
            <a href="{{ route('products.sales') }}" class="item" aria-label="sales">特價</a>
            <a href="{{ route('products.most-reviewed') }}" class="item" aria-label="most-reviewed">熱門</a>
            <a href="{{ route('products.news') }}" class="item" aria-label="news">新品</a>
            <a href="{{ route('products.multi-buys') }}" class="item" aria-label="multi-buys">合購</a>
            <a href="{{ route('products.stockouts') }}" class="item" aria-label="stockouts">無庫存</a>
        </div>
        <div class="mobile only right menu">
            <div class="ts item dropdown">
                <div class="text">
                    前往
                </div>
                <i class="dropdown icon"></i>
                <div class="menu">
                    <a href="{{ route('home') }}" class="item" aria-label="home">
                        <i class="home icon"></i>首頁
                    </a>
                    <a href="{{ route('products.limited-offers') }}" class="item" aria-label="limited-offers">
                        <i class="certificate icon"></i>期間限定
                    </a>
                    <a href="{{ route('products.sales') }}" class="item" aria-label="sales">
                        <i class="shopping basket icon"></i>特價
                    </a>
                    <a href="{{ route('products.most-reviewed') }}" class="item" aria-label="most-reviewed">
                        <i class="comments outline icon"></i>熱門
                    </a>
                    <a href="{{ route('products.news') }}" class="item" aria-label="news">
                        <i class="leaf icon"></i>新品
                    </a>
                    <a href="{{ route('products.multi-buys') }}" class="item" aria-label="multi-buys">
                        <i class="cubes icon"></i>合購
                    </a>
                    <a href="{{ route('products.stockouts') }}" class="item" aria-label="stockouts">
                        <i class="archive icon"></i>無庫存
                    </a>
                    <div class="divider"></div>
                    <a href="{{ route('pages.changelog') }}" class="item" aria-label="changelog">
                        v1.34.0 更新日誌
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- / 頂部固定選單 -->
