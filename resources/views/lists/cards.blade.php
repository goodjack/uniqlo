@php
    $genders = ['men' => '男裝', 'women' => '女裝', 'kids' => '童裝', 'baby' => '嬰幼兒'];
    // 篩選過後常常只剩一兩種性別有商品，空的入口與空的區段都不該佔版面
    $available = collect($genders)->filter(fn($label, $key) => count($hmallProductList[$key]) > 0);
@endphp

<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        @if ($available->isEmpty())
            @include('partials.empty-state', [
                'icon' => 'search faded',
                'title' => '沒有符合的商品',
                'hint' => '試試看少選幾個條件',
            ])
        @else
            {{-- 捲到下面還看得到，才像導覽而不是一次性的按鈕 --}}
            <div class="ts small horizontally scrollable evenly divided flatted menu sticky-gender-menu"
                id="gender_menu">
                @foreach ($available as $key => $label)
                    <a class="horizontally fitted item" href="#{{ $key }}">
                        {{ $label }}
                        <div class="ts mini circular label" style="margin-left: 4px;">
                            {{ count($hmallProductList[$key]) }}
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="ts active basic horizontally fitted tab segment">
                @foreach ($available as $key => $label)
                    <h2 class="ts large header" id="{{ $key }}">
                        {{ $label }}
                        <div class="inline sub header">共 {{ count($hmallProductList[$key]) }} 件</div>
                    </h2>
                    <div class="ts doubling link cards four">
                        @foreach ($hmallProductList[$key] as $hmallProduct)
                            @include('hmall-products.card', ['hmallProduct' => $hmallProduct])
                        @endforeach
                    </div>

                    <div class="ts hidden divider"></div>

                    @if (!$loop->last)
                        <div class="ts hidden section divider"></div>
                    @endif
                @endforeach

                <div class="ts hidden divider"></div>
            </div>
        @endif
    </div>
</div>
