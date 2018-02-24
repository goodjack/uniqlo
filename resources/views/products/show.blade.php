@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', $product->name)

@section('metadata')
<meta property="og:image" content="{{ $product->main_image_url }}" />
@endsection

@section('content')
<!-- 片段 -->
<div class="ts flatted card">
    <div class="content">
        <div class="ts stackable grid">
            <div class="five wide column">
                <img class="ts fluid rounded link image" src="{{ $product->main_image_url }}">
            </div>
            <div class="eleven wide column">
                <h2 class="ts header">{{ $product->name }}</h2>
                {!! $productPresenter->getProductTag($product) !!}
                <p>{!! $product->comment !!}</p>
            </div>
        </div>
        <div class="row">
            <div class="right aligned extra content">
                <div class="ts separated buttons">
                    <button class="ts mini link button"><h4>NT${{ $product->price }}</h4></button>
                    <a class="ts mini negative basic labeled icon button" href="http://www.uniqlo.com/tw/store/goods/{{ $product->id }}" target="_blank"><i class="external link icon"></i> 前往官網</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ts doubling four waterfall cards">
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
    <div class="ts flatted card">
        <div class="image">
            <canvas id="priceChart" width="400" height="400"></canvas>
        </div>
    </div>
    {!! $productPresenter->getStyleDictionaryImages($styleDictionary) !!}
    {!! $productPresenter->getSubImages($product) !!}
</div>
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