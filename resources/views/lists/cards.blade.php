<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        <div class="ts small horizontally scrollable evenly divided flatted menu" id="gender_menu">
            @foreach (['men' => '男裝', 'women' => '女裝', 'kids' => '童裝', 'baby' => '嬰幼兒'] as $key => $label)
                <a class="horizontally fitted item" href="#{{ $key }}">
                    {{ $label }}
                    <div class="ts mini circular label" style="margin-left: 4px;">{{ count($hmallProductList[$key]) }}
                    </div>
                </a>
            @endforeach
        </div>
        <div class="ts active basic horizontally fitted tab segment">
            @foreach (['men' => '男裝', 'women' => '女裝', 'kids' => '童裝', 'baby' => '嬰幼兒'] as $key => $label)
                <h2 class="ts large header" id="{{ $key }}">
                    {{ $label }}
                    <div class="inline sub header">共 {{ count($hmallProductList[$key]) }} 件</div>
                </h2>
                @if (count($hmallProductList[$key]) > 0)
                    <div class="ts doubling link cards four">
                        @each('hmall-products.card', $hmallProductList[$key], 'hmallProduct')
                    </div>
                @else
                    沒有商品
                @endif

                <div class="ts hidden divider"></div>

                <a class="ts mini compact right floated labeled icon button" href="#gender_menu">
                    <i class="arrow up icon"></i>回到頂部
                </a>

                @if (!$loop->last)
                    <div class="ts hidden section divider"></div>
                @endif
            @endforeach

            <div class="ts hidden divider"></div>
        </div>
    </div>
</div>
