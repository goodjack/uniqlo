@inject('searchPresenter', 'App\Presenters\SearchPresenter')
@extends('layouts.master')

@section('title', $query)

@section('metadata')
{{--  <meta property="og:image" content="{{ $searchPresenter->getProductMainImageUrl($productInfo) }}" />  --}}
@endsection

@section('content')
    {!! $searchPresenter->getSearchResultCards($searchResults) !!}
@endsection