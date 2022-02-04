@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')
@extends('layouts.master')

@section('title', "${count} 件商品特價中")

@section('content')
    <div class="ts fluid slate">
        <i class="primary shopping basket icon"></i>
        <span class="header">{{ $count }} 件商品特價中</span>
    </div>

    @include('lists.cards', ['hmallProductList' => $hmallProductList])
@endsection
