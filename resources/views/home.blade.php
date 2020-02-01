@extends('layouts.master')

@section('title', "首頁")

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
        <form class="ts big form" action="{{ route('products.go') }}">
            <input name="id" class="ts fluid input" type="number" placeholder="輸入 UNIQLO 商品編號比價...">
            <div class="ts hidden divider"></div>
            <center>
                <button class="ts button" type="submit">查看商品</button>
            </center>
        </form>
        <div class="ts hidden divider"></div>
        <div class="ts hidden divider"></div>
        <div class="ts hidden divider"></div>
        <div class="ts hidden divider"></div>
    </div>
</div>
@endsection
