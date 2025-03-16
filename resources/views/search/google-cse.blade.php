@extends('layouts.master')

@section('title', '關鍵字搜尋')

@section('content')
    <x-section veryNarrow tertiary>
        <h1 class="ts big header">
            <div class="content">
                關鍵字搜尋
            </div>
        </h1>
        <script async src="https://cse.google.com/cse.js?cx=a6fb9b0f56e1a9712"></script>
        <div class="gcse-searchbox"></div>
    </x-section>
    <x-section padded="normal">
        <div class="gcse-searchresults"></div>
    </x-section>
@endsection

@section('css')
    <style>
        .gsc-search-button-v2 svg {
            vertical-align: middle;
        }
    </style>
@endsection
