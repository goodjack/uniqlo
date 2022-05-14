@extends('layouts.master')

@section('title', '關鍵字搜尋')

@section('content')
    <div class="ts attached very padded fluid tertiary segment">
        <div class="ts very narrow container">
            <h1 class="ts big header">
                <div class="content">
                    關鍵字搜尋
                </div>
            </h1>
            <script async src="https://cse.google.com/cse.js?cx=a6fb9b0f56e1a9712"></script>
            <div class="gcse-searchbox"></div>
        </div>
    </div>
    <div class="ts attached padded horizontally fitted fluid segment">
        <div class="ts container">
            <div class="gcse-searchresults"></div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .gsc-search-button-v2 svg {
            vertical-align: middle;
        }

    </style>
@endsection
