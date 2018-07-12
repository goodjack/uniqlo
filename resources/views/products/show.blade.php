@inject('productPresenter', 'App\Presenters\ProductPresenter')
@extends('layouts.master')

@section('title', $product->name)

@section('metadata')
<meta property="og:image" content="{{ $product->main_image_url }}" />
@endsection

@section('content')
<div class="ts very padded horizontally fitted attached fluid segment">
    <div class="ts narrow container relaxed grid">
        <div class="nine wide computer nine wide tablet sixteen wide mobile column">
            <div class="ts fluid container">
                <a class="ts centered image" href="{{ $product->main_image_url }}" target="_blank">
                    <img class="ts centered image" src="{{ $product->main_image_url }}">
                </a>
            </div>
        </div>
        <div class="seven wide computer seven wide tablet sixteen wide mobile column">
            <div class="ts fluid very narrow container grid">
                <div class="sixteen wide column">
                    <h3 class="ts dividing header">
                        {{ $product->name }}
                        <div class="sub header">商品編號 {{ $product->id }}</div>
                    </h3>
                </div>
                <div class="sixteen wide center aligned column">
                    <div class="ts very narrow container">
                        <div class="ts basic fitted segment">
                            {!! $productPresenter->getProductTag($product) !!}
                        </div>
                    </div>
                </div>
                <div class="eight wide column">
                    <div class="ts tiny divided horizontal two statistics">
                        <div class="statistic" style="width: 100%; justify-content: center;">
                            <div class="value">{{ $product->max_price }}</div>
                            <div class="label">歷史高價 <i class="fitted caret up icon"></i></div>
                        </div>
                        <div class="statistic" style="width: 100%; justify-content: center;">
                            <div class="value">{{ $product->min_price }}</div>
                            <div class="label">歷史低價 <i class="fitted caret down icon"></i></div>
                        </div>
                    </div>
                </div>
                <div class="eight wide column">
                    <div class="ts borderless card">
                        <div class="center aligned content">
                            <div class="ts small statistic">
                                <div class="value">{{ $product->price }}</div>
                                <div class="label">現在售價</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ts flatted card">
                <div class="image">
                    <canvas id="priceChart" width="457" height="263"></canvas>
                </div>
            </div>
            <div class="ts flatted vertically fitted segment">
                <details class="ts accordion">
                    <summary>
                        <i class="dropdown icon"></i>商品介紹
                    </summary>
                    <div class="content">
                        <p>{!! $product->comment !!}</p>
                    </div>
                </details>
            </div>
            <div class="ts grid">
                <div class="six wide computer six wide tablet sixteen wide mobile right floated column">
                    <div class="ts fitted clearing flatted borderless segment">
                        <a class="ts small basic circular fluid button" href="http://www.uniqlo.com/tw/store/goods/{{ $product->id }}" target="_blank">前往官網</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if (count($styleDictionaries) > 0)
<div class="ts very padded horizontally fitted attached fluid tertiary segment">
    <div class="ts narrow container">
        <div class="ts large dividing header">精選穿搭</div>
        <br>
        <div class="ts doubling four waterfall flatted cards">
            {!! $productPresenter->getStyleDictionaries($styleDictionaries) !!}
        </div>
    </div>
</div>
@endif

@if (count(json_decode($product->colors)) > 0 || count(json_decode($product->sub_images)) > 0)
<div class="ts very padded horizontally fitted attached fluid tertiary segment">
    <div class="ts narrow container">
        <div class="ts large dividing header">商品實照</div>
        <br>
        <div class="ts doubling four waterfall flatted cards">
            {!! $productPresenter->getSubImages($product) !!}
            {!! $productPresenter->getItemImages($product) !!}
        </div>
    </div>
</div>
@endif
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