{{--
    清單頁的正文：依性別分段的卡片 grid，或者一句話的空狀態。

    genders  ['men' => '男裝', ...] 四個性別的完整清單，在 lists/list.blade.php
             算好才傳進來——章節選單跟這裡共用同一份，兩邊不能各自濾一次然後
             濾出不同的段落。
    count    篩選後的總件數。一件都沒有時整頁換成空狀態，不是列四段「沒有商品」。
--}}
@if ($count === 0)
    @include('partials.empty-state', [
        'icon' => 'search faded',
        'title' => '沒有符合的商品',
        'hint' => '試試看少選幾個條件',
    ])
@else
    {{-- 段與段之間的距離由 .uq-h2 的上緣間距負責，不再靠一層 tab segment 加幾條隱形分隔線 --}}
    @foreach ($genders as $key => $label)
        @include('partials.section-anchor', ['anchor' => $key, 'menu' => 'gender_menu'])
        <h2 class="unstyled uq-h2">
            {{ $label }}
            <span class="uq-count">{{ count($hmallProductList[$key]) }} 件</span>
        </h2>
        @if (count($hmallProductList[$key]) > 0)
            <div class="ts doubling link cards four">
                @foreach ($hmallProductList[$key] as $hmallProduct)
                    @include('hmall-products.card', ['hmallProduct' => $hmallProduct])
                @endforeach
            </div>
        @else
            {{-- master 在這裡就是一句「沒有商品」：這一段有沒有貨也是資訊 --}}
            <p class="uq-none">沒有商品</p>
        @endif
    @endforeach
@endif
