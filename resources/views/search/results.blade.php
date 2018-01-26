@inject('searchPresenter', 'App\Presenters\SearchPresenter')
@extends('layouts.master')

@section('title', $query . " 的搜尋結果")

@section('metadata')
{{--  <meta property="og:image" content="{{ $searchPresenter->getProductMainImageUrl($productInfo) }}" />  --}}
@endsection

@section('content')
    <div class="row">
        <div class="ts slate">
            <span class="header">{{ $query }} 的搜尋結果</span>
        </div>
    </div>

    <div class="ts hidden divider">我是分隔線</div>

    {!! $searchPresenter->getSearchResultCards($searchResults) !!}
@endsection