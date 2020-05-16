@extends('layouts.master')

@section('title')
@yield('title')
@endsection

@section('content')
<div class="ts massive borderless basic very padded horizontally fitted slate">
    <i class="frown faded icon"></i>
    <span class="header">@yield('code', __('Oh no'))</span>
    <span class="description">@yield('message')</span>
</div>
@endsection
