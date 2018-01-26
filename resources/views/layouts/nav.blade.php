<!-- 頂部固定選單 -->
<div class="ts mini fluid top fixed horizontally link inverted negative menu">
    <div class="ts narrow container">
        <a href="#" class="item">
            <i class="big fitted search icon"></i>
        </a>
        <div class="mobile only stretched item">
            @include('layouts.search-bar')
        </div>
        <div class="tablet or large device only item">
            @include('layouts.search-bar')
        </div>
        <div class="tablet or large device only right menu">
            <a href="#" class="item">
                <img class="ts avatar image" src="https://tocas-ui.com/assets/img/5e5e3a6.png">
                <span>Jack</span>
            </a>
            <a href="#" class="item">首頁</a>
            <div class="ts icon buttons item">
                <button class="ts button">
                    <i class="users icon"></i>
                </button>
                <button class="ts button">
                    <i class="comments icon"></i>
                </button>
                <button class="ts button">
                    <i class="globe icon"></i>
                </button>
            </div>
            <div class="item">
                <button class="ts icon button">
                    <i class="help circle icon"></i>
                </button>
            </div>
            <div class="ts item dropdown">
                <i class="dropdown icon"></i>
                <div class="menu">
                    <a class="item">按鈕</a>
                    <div class="divider"></div>
                    <a class="item">表單</a>
                    <a class="item">導航列</a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- / 頂部固定選單 -->