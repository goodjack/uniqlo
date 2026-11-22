@inject('outfitStatsPresenter', 'App\Presenters\OutfitStatsPresenter')

@php
    $cards = $outfitStatsPresenter->cards($product, $stats);
    $sizeTable = $outfitStatsPresenter->sizeTable($stats);
    $isSampled = $stats['outfits'] >= \App\Services\OutfitStatsService::SAMPLE_LIMIT;
@endphp

{{-- 每張卡與尺寸表都沒過門檻時整塊不出現，不留只有標題的空區塊 --}}
@if ($cards !== [] || $sizeTable !== null)
<div class="ts very padded horizontally fitted attached fluid tertiary segment">
    <div class="ts container">
        <h2 class="ts large dividing header">
            穿搭統計
            <div class="inline sub header">依{{ $isSampled ? '最近 ' : ' ' }}{{ $stats['outfits'] }} 則網友穿搭</div>
        </h2>
        @if ($cards !== [])
            <div class="ts hidden divider"></div>
            <div class="ts doubling three cards uq-outfit-stats">
                @foreach ($cards as $card)
                    <div class="ts card">
                        <div class="content">
                            <div class="header">{{ $card['title'] }}</div>
                            @if ($card['base'] !== null)
                                <div class="meta">{{ $card['base'] }} 則有資料</div>
                            @endif
                            <div class="description">
                                @foreach ($card['rows'] as $row)
                                    <div class="uq-outfit-stat">
                                        <div class="uq-outfit-stat-label">
                                            <span>{{ $row['label'] }}</span>
                                            <span>{{ $row['percent'] }}%</span>
                                        </div>
                                        <div class="ts tiny progress">
                                            <div class="bar" style="width: {{ $row['percent'] }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        @if ($sizeTable !== null)
            {{-- 用跟上面卡片同一種小標，字級才一致 --}}
            <div class="ts fluid card">
                <div class="content">
                    <div class="header">不同身高穿的尺寸</div>
                </div>
                <table class="ts borderless unstackable compact table uq-outfit-size-table">
                    <thead>
                        <tr>
                            <th>穿的人</th>
                            <th>則數</th>
                            <th>穿的尺寸</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sizeTable['rows'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td>{{ $row['outfits'] }}</td>
                                <td>
                                    @foreach ($row['sizes'] as $size)
                                        <span class="uq-outfit-size">{{ $size['size'] }} {{ $size['percent'] }}%</span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endif
