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
            <a href="{{ route('lists.limited-offers') }}" class="item" aria-label="limited-offers">期間限定</a>
            <a href="{{ route('lists.sale') }}" class="item" aria-label="sale">特價</a>
            <a href="{{ route('lists.most-reviewed') }}" class="item" aria-label="most-reviewed">熱門</a>
            <a href="{{ route('lists.new') }}" class="item" aria-label="new">新品</a>
            <a href="{{ route('lists.coming-soon') }}" class="item" aria-label="coming-soon">即將上市</a>
            <a href="{{ route('lists.multi-buy') }}" class="item" aria-label="multi-buy">合購</a>
            <a href="{{ route('lists.online-special') }}" class="item" aria-label="online-special">網路獨家</a>
            <div class="divider"></div>
            <a href="{{ route('pages.changelog') }}" class="item" aria-label="changelog">
                v2.1.0 更新日誌
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
                    <a href="{{ route('lists.limited-offers') }}" class="item" aria-label="limited-offers">
                        <i class="certificate icon"></i>期間限定
                    </a>
                    <a href="{{ route('lists.sale') }}" class="item" aria-label="sale">
                        <i class="shopping basket icon"></i>特價
                    </a>
                    <a href="{{ route('lists.most-reviewed') }}" class="item" aria-label="most-reviewed">
                        <i class="comments outline icon"></i>熱門
                    </a>
                    <a href="{{ route('lists.new') }}" class="item" aria-label="new">
                        <i class="leaf icon"></i>新品
                    </a>
                    <a href="{{ route('lists.coming-soon') }}" class="item" aria-label="coming-soon">
                        <i class="checked calendar icon"></i>即將上市
                    </a>
                    <a href="{{ route('lists.multi-buy') }}" class="item" aria-label="multi-buy">
                        <i class="cubes icon"></i>合購
                    </a>
                    <a href="{{ route('lists.online-special') }}" class="item" aria-label="online-special">
                        <i class="tv icon"></i>網路獨家
                    </a>
                    <div class="divider"></div>
                    <a href="{{ route('pages.changelog') }}" class="item" aria-label="changelog">
                        v2.1.0 更新日誌
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- / 頂部固定選單 -->
