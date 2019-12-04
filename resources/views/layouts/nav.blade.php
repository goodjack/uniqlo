<!-- 頂部固定選單 -->
<div class="ts top fixed borderless small link menu">
    <div class="ts narrow container">
        <a href="{{ route('home') }}" class="header item">
            <i class="large negative clone icon"></i>UQ 搜尋
        </a>
        <div class="mobile only stretched item">
            @include('layouts.search-bar')
        </div>
        <div class="tablet or large device only item">
            @include('layouts.search-bar')
        </div>
        <div class="tablet or large device only right menu">
            <a href="{{ route('home') }}" class="item">首頁</a>
            <a href="{{ route('products.limited-offers') }}" class="item">期間限定</a>
            <a href="{{ route('products.sales') }}" class="item">特價</a>
            <a href="{{ route('products.multi-buys') }}" class="item">合購</a>
            <a href="{{ route('products.news') }}" class="item">新品</a>
            <a href="{{ route('products.stockouts') }}" class="item">無庫存</a>
        </div>
    </div>
</div>
<!-- / 頂部固定選單 -->
