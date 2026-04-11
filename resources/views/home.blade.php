@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')
@extends('layouts.master')

@section('title', '首頁')

@section('content')
    <x-section veryNarrow>
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
    </x-section>

    @if ($mostVisitedProducts->isNotEmpty())
        <x-section title="大家都在看" subTitle="最多人瀏覽的商品" padded="normal">
            <div class="ts doubling link cards six">
                @each('hmall-products.simple-card', $mostVisitedProducts, 'hmallProduct')
            </div>
        </x-section>
    @endif
@endsection
