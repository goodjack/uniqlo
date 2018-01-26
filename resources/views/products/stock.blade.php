@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', $productPresenter->getProductName($productInfo))

@section('metadata')
<meta property="og:image" content="{{ $productPresenter->getProductMainImageUrl($productInfo) }}" />
@endsection

@section('content')
<!-- 片段 -->
<div class="ts card">
    <div class="content">
        <div class="ts stackable grid">
            <div class="two wide column">
                {!! $productPresenter->getProductMainImage($productInfo) !!}
            </div>
            <div class="fourteen wide column">
                {!! $productPresenter->getProductHeader($productInfo) !!}
            </div>
        </div>
    </div>
</div>

<div class="row">
    <table class="ts table">
        <thead>
            <tr>
                <th>名稱</th>
                <th>英文名稱</th>
                <th>計畫狀態</th>
                <th>說明</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>可憐</td>
                <td>Karen</td>
                <td class="positive"><i class="check icon"></i> 已完成</td>
                <td>多國語元支援的函式庫，協助網站跨國交際。</td>
            </tr>
            <tr class="positive">
                <td>美由紀</td>
                <td>Miyuki</td>
                <td><i class="check icon"></i> 已完成</td>
                <td>協助圖像處理的類別，必須要安裝 Imagick。</td>
            </tr>
            <tr>
                <td>卡莉絲</td>
                <td>Caris</td>
                <td class="negative"><i class="dont icon"></i> 尚未完成</td>
                <td>一個基於 HTML5 的遊戲引擎。</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection