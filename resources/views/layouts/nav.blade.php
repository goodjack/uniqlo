<!-- 頂部固定選單 -->
<div class="ts top fixed small link menu">
    <div class="ts container">
        <a href="{{ route('home') }}" class="header item">
            <i class="large negative clone icon"></i>UQ 搜尋
        </a>
        <div class="stretched item">
            @include('layouts.search-bar')
        </div>
        <div class="large screen only stretched item">
        </div>
        <div class="tablet or large device only right menu">
            <a href="{{ route('home') }}" class="item" aria-label="home">首頁</a>
            <a href="{{ route('lists.limited-offers', request()->only('brand')) }}" class="item"
                aria-label="limited-offers">期間限定</a>
            <a href="{{ route('lists.sale', request()->only('brand')) }}" class="item" aria-label="sale">特價</a>
            <a href="{{ route('lists.most-reviewed', request()->only('brand')) }}" class="item"
                aria-label="most-reviewed">熱門評論</a>
            <a href="{{ route('lists.japan-most-reviewed', request()->only('brand')) }}" class="item"
                aria-label="japan-most-reviewed">日本評論</a>
            <a href="{{ route('lists.top-wearing', request()->only('brand')) }}" class="item"
                aria-label="top-wearing">熱門穿搭</a>
            <a href="{{ route('lists.most-visited', request()->only('brand')) }}" class="item"
                aria-label="most-visited">熱門瀏覽</a>
            <div class="ts item dropdown">
                <div class="text">
                    更多
                </div>
                <i class="dropdown icon"></i>
                <div class="menu">
                    <a href="{{ route('lists.new', request()->only('brand')) }}" class="item" aria-label="new">
                        <i class="leaf icon"></i>新品
                    </a>
                    <a href="{{ route('lists.coming-soon', request()->only('brand')) }}" class="item"
                        aria-label="coming-soon">
                        <i class="checked calendar icon"></i>即將上市
                    </a>
                    <a href="{{ route('lists.multi-buy', request()->only('brand')) }}" class="item"
                        aria-label="multi-buy">
                        <i class="cubes icon"></i>合購
                    </a>
                    <a href="{{ route('lists.online-special', request()->only('brand')) }}" class="item"
                        aria-label="online-special">
                        <i class="tv icon"></i>網路獨家
                    </a>
                </div>
            </div>
            <div class="divider"></div>
            <a href="{{ route('pages.changelog') }}" class="item" aria-label="changelog">
                v4.1.0 更新日誌
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
                    <a href="{{ route('lists.limited-offers', request()->only('brand')) }}" class="item"
                        aria-label="limited-offers">
                        <i class="certificate icon"></i>期間限定
                    </a>
                    <a href="{{ route('lists.sale', request()->only('brand')) }}" class="item" aria-label="sale">
                        <i class="shopping basket icon"></i>特價
                    </a>
                    <a href="{{ route('lists.most-reviewed', request()->only('brand')) }}" class="item"
                        aria-label="most-reviewed">
                        <i class="comments outline icon"></i>熱門評論
                    </a>
                    <a href="{{ route('lists.japan-most-reviewed', request()->only('brand')) }}" class="item"
                        aria-label="most-reviewed">
                        <i class="comments outline icon"></i>日本熱門評論
                    </a>
                    <a href="{{ route('lists.top-wearing', request()->only('brand')) }}" class="item"
                        aria-label="top-wearing">
                        <i class="camera retro icon"></i>熱門穿搭
                    </a>
                    <a href="{{ route('lists.most-visited', request()->only('brand')) }}" class="item"
                        aria-label="most-visited">
                        <i class="chart line icon"></i>熱門瀏覽
                    </a>
                    <a href="{{ route('lists.new', request()->only('brand')) }}" class="item" aria-label="new">
                        <i class="leaf icon"></i>新品
                    </a>
                    <a href="{{ route('lists.coming-soon', request()->only('brand')) }}" class="item"
                        aria-label="coming-soon">
                        <i class="checked calendar icon"></i>即將上市
                    </a>
                    <a href="{{ route('lists.multi-buy', request()->only('brand')) }}" class="item"
                        aria-label="multi-buy">
                        <i class="cubes icon"></i>合購
                    </a>
                    <a href="{{ route('lists.online-special', request()->only('brand')) }}" class="item"
                        aria-label="online-special">
                        <i class="tv icon"></i>網路獨家
                    </a>
                    <div class="divider"></div>
                    <a href="{{ route('pages.changelog') }}" class="item" aria-label="changelog">
                        v4.1.0 更新日誌
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- / 頂部固定選單 -->
