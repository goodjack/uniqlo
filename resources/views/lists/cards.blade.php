<div class="ts attached padded horizontally fitted fluid segment">
    <div class="ts container">
        <div class="ts small evenly divided flatted menu anchor" id="gender_menu">
            <a class="item" href="#men">
                男裝
                <div class="ts mini label">{{ count($hmallProductList['men']) }}</div>
            </a>
            <a class="item" href="#women">
                女裝
                <div class="ts mini label">{{ count($hmallProductList['women']) }}</div>
            </a>
            <a class="item" href="#kids">
                童裝
                <div class="ts mini label">{{ count($hmallProductList['kids']) }}</div>
            </a>
            <a class="item" href="#baby">
                嬰幼兒
                <div class="ts mini label">{{ count($hmallProductList['baby']) }}</div>
            </a>
        </div>
        <div class="ts active basic horizontally fitted tab segment">
            <h2 class="ts large header anchor" id="men">
                男裝
                <div class="inline sub header">共 {{ count($hmallProductList['men']) }} 件</div>
            </h2>
            @if (count($hmallProductList['men']) > 0)
                <div class="ts doubling link cards four">
                    @each('hmall-products.card', $hmallProductList['men'], 'hmallProduct')
                </div>
            @else
                沒有商品
            @endif

            <div class="ts hidden divider"></div>

            <a class="ts mini compact right floated labeled icon button" href="#gender_menu">
                <i class="arrow up icon"></i>回到頂部
            </a>

            <div class="ts hidden section divider"></div>

            <h2 class="ts large header anchor" id="women">
                女裝
                <div class="inline sub header">共 {{ count($hmallProductList['women']) }} 件</div>
            </h2>
            @if (count($hmallProductList['women']) > 0)
                <div class="ts doubling link cards four">
                    @each('hmall-products.card', $hmallProductList['women'], 'hmallProduct')
                </div>
            @else
                沒有商品
            @endif

            <div class="ts hidden divider"></div>

            <a class="ts mini compact right floated labeled icon button" href="#gender_menu">
                <i class="arrow up icon"></i>回到頂部
            </a>

            <div class="ts hidden section divider"></div>

            <h2 class="ts large header anchor" id="kids">
                童裝
                <div class="inline sub header">共 {{ count($hmallProductList['kids']) }} 件</div>
            </h2>
            @if (count($hmallProductList['kids']) > 0)
                <div class="ts doubling link cards four">
                    @each('hmall-products.card', $hmallProductList['kids'], 'hmallProduct')
                </div>
            @else
                沒有商品
            @endif

            <div class="ts hidden divider"></div>

            <a class="ts mini compact right floated labeled icon button" href="#gender_menu">
                <i class="arrow up icon"></i>回到頂部
            </a>

            <div class="ts hidden section divider"></div>

            <h2 class="ts large header anchor" id="baby">
                嬰幼兒
                <div class="inline sub header">共 {{ count($hmallProductList['baby']) }} 件</div>
            </h2>
            @if (count($hmallProductList['baby']) > 0)
                <div class="ts doubling link cards four">
                    @each('hmall-products.card', $hmallProductList['baby'], 'hmallProduct')
                </div>
            @else
                沒有商品
            @endif

            <div class="ts hidden divider"></div>

            <a class="ts mini compact right floated labeled icon button" href="#gender_menu">
                <i class="arrow up icon"></i>回到頂部
            </a>

            <div class="ts hidden divider"></div>
        </div>
    </div>
</div>
