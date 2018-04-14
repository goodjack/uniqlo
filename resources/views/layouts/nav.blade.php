<!-- 頂部固定選單 -->
<div class="ts top fixed borderless small link menu">
    <div class="ts narrow container">
        <a href="#" class="item">
            <i class="big fitted negative square icon"></i>
            &nbsp;
            <i class="big fitted negative square icon"></i>
        </a>
        <div class="mobile only stretched item">
            @include('layouts.search-bar')
        </div>
        <div class="tablet or large device only fitted item">
            @include('layouts.search-bar')
        </div>
        <div class="tablet or large device only right menu">
            <a href="#" class="item">首頁</a>
            <a href="{{ action('ProductController@stockouts') }}" class="item">無庫存列表</a>
            <a href="{{ action('ProductController@sales') }}" class="item">特價列表</a>
        </div>
    </div>
</div>
<!-- / 頂部固定選單 -->