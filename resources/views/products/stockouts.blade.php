@extends('layouts.master')

@section('title', "可能已經沒有庫存")

@section('metadata')
{{--  <meta property="og:image" content="{{ $productPresenter->getProductMainImageUrl($productInfo) }}" />  --}}
@endsection

@section('content')
    <div class="ts fluid slate">
        <i class="archive faded icon"></i>
        <span class="header">可能已經沒有庫存</span>
    </div>

    <br>

    <div class="ts narrow container grid">
        @include('products.cards', ['products' => $stockouts])
    </div>
@endsection