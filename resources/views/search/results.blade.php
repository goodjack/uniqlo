@extends('layouts.master')

@section('title', "商品編號 {$query} 的搜尋結果")

@section('metadata')
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="search faded icon"></i>
        <span class="header">商品編號 {{ $query }} 的搜尋結果</span>
    </div>
    @include('search.cards', ['hmallProducts' => $hmallProducts, 'products' => $products])
@endsection
