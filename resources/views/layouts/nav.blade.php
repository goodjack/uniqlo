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
            <div class="divider"></div>
            <a href="{{ route('pages.changelog') }}" class="item" aria-label="changelog">
                v2.0.0 更新日誌
            </a>
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
                    <div class="divider"></div>
                    <a href="{{ route('pages.changelog') }}" class="item" aria-label="changelog">
                        v2.0.0 更新日誌
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- / 頂部固定選單 -->
