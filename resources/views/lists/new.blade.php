@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')
@extends('layouts.master')

@section('title', "${count} 件新款商品")

@section('content')
    <div class="ts fluid slate">
        <i class="positive leaf icon"></i>
        <span class="header">{{ $count }} 件新款商品</span>
    </div>

    @include('lists.cards', ['hmallProductList' => $hmallProductList])
@endsection
