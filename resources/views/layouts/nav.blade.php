@php
    $navLinks = config('nav.links');
    $navGroups = config('nav.groups');

    $brandQuery = request()->only('brand');
    $isCurrent = fn(string $route) => request()->routeIs($route);
    $groupIsCurrent = fn(array $items) => collect($items)->contains(fn($item) => request()->routeIs($item['route']));

    // 桌機：pinned 的清單放外層，下拉裡就不再列一次
    $pinnedItems = collect($navGroups)
        ->flatten(1)
        ->filter(fn($item) => $item['pinned'] ?? false)
        ->values();
    $dropdownItems = fn(array $items) => array_values(array_filter($items, fn($item) => !($item['pinned'] ?? false)));
@endphp

<!-- 頂部固定選單 -->
<div class="ts top fixed small link menu">
    <div class="ts container">
        {{-- Logo 本身就是回首頁的入口，桌機不另外放一個「首頁」 --}}
        <a href="{{ route('home') }}" class="header item" @if ($isCurrent('home')) aria-current="page" @endif>
            <i class="large negative clone icon"></i>UQ 搜尋
        </a>
        <div class="stretched item">
            @include('layouts.search-bar')
        </div>
        <div class="large screen only stretched item">
        </div>

        <div class="tablet or large device only right menu">
            <a href="{{ route($navLinks['categories']['route']) }}" class="item {{ $isCurrent('categories.*') ? 'active' : '' }}"
                @if ($isCurrent('categories.*')) aria-current="page" @endif>{{ $navLinks['categories']['label'] }}</a>

            @foreach ($pinnedItems as $item)
                <a href="{{ route($item['route'], $brandQuery) }}" class="item {{ $isCurrent($item['route']) ? 'active' : '' }}"
                    @if ($isCurrent($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach

            @foreach ($navGroups as $groupName => $items)
                @php($items = $dropdownItems($items))
                @continue(empty($items))
                <div class="ts item dropdown {{ $groupIsCurrent($items) ? 'active' : '' }}">
                    <div class="text">{{ $groupName }}</div>
                    <i class="dropdown icon"></i>
                    <div class="menu">
                        @foreach ($items as $item)
                            <a href="{{ route($item['route'], $brandQuery) }}" class="item"
                                @if ($isCurrent($item['route'])) aria-current="page" @endif>
                                <i class="{{ $item['icon'] }} icon"></i>{{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <a href="{{ route($navLinks['favorites']['route']) }}" class="item {{ $isCurrent('favorites.*') ? 'active' : '' }}"
                @if ($isCurrent('favorites.*')) aria-current="page" @endif>{{ $navLinks['favorites']['label'] }}</a>

            <div class="divider"></div>
            <a href="{{ route($navLinks['changelog']['route']) }}" class="item {{ $isCurrent('pages.changelog') ? 'active' : '' }}"
                @if ($isCurrent('pages.changelog')) aria-current="page" @endif>{{ $navLinks['changelog']['label'] }}</a>
        </div>

        <div class="mobile only right menu">
            {{-- 手機維持單一選單，裡面用靜態小標題分組，不做多層下拉 --}}
            <div class="ts item dropdown">
                <div class="text">選單</div>
                <i class="dropdown icon"></i>
                <div class="menu">
                    {{-- 手機的 Logo 小又擠在搜尋框旁邊，「首頁」在選單裡才點得到 --}}
                    <a href="{{ route('home') }}" class="item" @if ($isCurrent('home')) aria-current="page" @endif>
                        <i class="home icon"></i>首頁
                    </a>
                    <a href="{{ route($navLinks['categories']['route']) }}" class="item"
                        @if ($isCurrent('categories.*')) aria-current="page" @endif>
                        <i class="{{ $navLinks['categories']['icon'] }} icon"></i>{{ $navLinks['categories']['label'] }}
                    </a>
                    <a href="{{ route($navLinks['favorites']['route']) }}" class="item"
                        @if ($isCurrent('favorites.*')) aria-current="page" @endif>
                        <i class="{{ $navLinks['favorites']['icon'] }} icon"></i>{{ $navLinks['favorites']['label'] }}
                    </a>
                    @foreach ($navGroups as $groupName => $items)
                        <div class="divider"></div>
                        <div class="header">{{ $groupName }}</div>
                        @foreach ($items as $item)
                            <a href="{{ route($item['route'], $brandQuery) }}" class="item"
                                @if ($isCurrent($item['route'])) aria-current="page" @endif>
                                <i class="{{ $item['icon'] }} icon"></i>{{ $item['label'] }}
                            </a>
                        @endforeach
                    @endforeach
                    <div class="divider"></div>
                    <a href="{{ route($navLinks['changelog']['route']) }}" class="item"
                        @if ($isCurrent('pages.changelog')) aria-current="page" @endif>
                        {{ $navLinks['changelog']['label'] }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- / 頂部固定選單 -->
