@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', $product->name)

@section('metadata')
<meta property="og:image" content="{{ $product->main_image_url }}" />
@endsection

@section('content')
<div class="ts big slate">
    <div class="ts narrow container">
        <span class="header">{{ $product->name }}</span>
        <div class="action">
            <div class="ts separated buttons">
                <button class="ts mini link button"><h4>NT${{ $product->price }}</h4></button>
                <button class="ts mini basic circular disabled button">追蹤</button>
                <a class="ts mini negative basic circular button" href="http://www.uniqlo.com/tw/store/goods/{{ $product->id }}" target="_blank">前往官網</a>
            </div>
        </div>
    </div>
</div>

<br>

<!-- 主要內容網格容器 -->
<div class="ts narrow container grid">
    <div class="four wide computer four wide tablet sixteen wide mobile column">
        <div class="ts card">
            <a class="image" href="{{ $product->main_image_url }}" target="_blank">
                <img src="{{ $product->main_image_url }}">
            </a>
            <a class="content" href="http://www.uniqlo.com/tw/store/goods/{{ $product->id }}" target="_blank">
                <div class="header">{{ $product->name }}</div>
                <div class="meta">
                    <div>{{ $product->id }}</div>
                </div>
                <div class="description">
                    {!! $product->comment !!}
                </div>
            </a>
            <a class="center aligned extra content" href="http://www.uniqlo.com/tw/store/goods/{{ $product->id }}" target="_blank">
                {!! $productPresenter->getProductTag($product) !!}
                <div class="ts basic fitted segment">
                    <h5 class="ts center aligned header">NT${{ $product->price }}</h5>
                </div>
            </a>
        </div>
    </div>
    <div class="twelve wide computer twelve wide tablet sixteen wide mobile column">
        <div class="ts grid">
            <div class="six wide computer six wide tablet sixteen wide mobile column">
                <div class="ts grid">
                    <div class="sixteen wide computer sixteen wide tablet eight wide mobile column">
                        <div class="ts flatted card">
                            <div class="center aligned content">
                                <div class="ts negative big statistic">
                                    <div class="value">{{ $highestPrice }}</div>
                                    <div class="label">歷史高價</div>
                                </div>
                            </div>
                            <div class="symbol">
                                <i class="arrow up icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="sixteen wide computer sixteen wide tablet eight wide mobile column">
                        <div class="ts flatted card">
                            <div class="center aligned content">
                                <div class="ts positive big statistic">
                                    <div class="value">{{ $lowestPrice }}</div>
                                    <div class="label">歷史低價</div>
                                </div>
                            </div>
                            <div class="symbol">
                                <i class="arrow down icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ten wide computer ten wide tablet sixteen wide mobile column">
                <div class="ts flatted card">
                    <div class="image">
                        <canvas id="priceChart" width="457" height="263"></canvas>
                    </div>
                </div>
            </div>
            <div class="sixteen wide column">
                <div class="ts doubling four waterfall cards">
                    {!! $productPresenter->getStyleDictionaries($styleDictionaries) !!}
                    {!! $productPresenter->getSubImages($product) !!}
                </div>
            </div>
        </div>
    </div>
</div>
<!-- / 主要內容網格容器 -->
@endsection

@section('javascript')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.min.js" integrity="sha256-c0m8xzX5oOBawsnLVpHnU2ieISOvxi584aNElFl2W6M=" crossorigin="anonymous"></script>

<script>
    var ctx = document.getElementById("priceChart");
    var priceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! $productPresenter->getPriceChartLabels($productHistories) !!},
            datasets: [{
                label: '價格',
                data: {!! $productPresenter->getPriceChartData($productHistories) !!},
                backgroundColor: 'rgba(206, 94, 87, 0.2)',
                borderColor: 'rgba(206, 94, 87, 1.0)',
                borderWidth: 1,
                cubicInterpolationMode: 'monotone'
            }]
        },
        options: {
            title: {
                display: true,
                text: '歷史價格折線圖'
            },
            legend: {
                display: false
            },
            tooltips: {
                backgroundColor: 'rgba(0, 0, 0, 0.7)',
                xPadding: 11,
                yPadding: 8,
                titleMarginBottom: 10,
                titleFontSize: 14,
                bodyFontSize: 15,
                displayColors: false,
                callbacks: {
                    label: function(tooltipItem, data) {
                        return data.datasets[tooltipItem.datasetIndex].label + "：NT$" + tooltipItem.yLabel;
                    }
                }
            },
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
</script>
@endsection