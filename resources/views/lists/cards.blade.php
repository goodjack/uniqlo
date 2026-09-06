{{--
    清單頁的正文：依性別分段的卡片 grid，或者一句話的空狀態。

    availableGenders  ['men' => '男裝', ...] 篩過的 Collection，只留有商品
                      的那幾個性別。章節選單跟這裡共用同一份，在 lists/list.blade.php
                      算好才傳進來——選單站在這個 container 外面，兩邊不能
                      各自濾一次然後濾出不同的段落。
--}}
@if ($availableGenders->isEmpty())
    @include('partials.empty-state', [
        'icon' => 'search faded',
        'title' => '沒有符合的商品',
        'hint' => '試試看少選幾個條件',
    ])
@else
    {{-- 段與段之間的距離由 .uq-h2 的上緣間距負責，不再靠一層 tab segment 加幾條隱形分隔線 --}}
    @foreach ($availableGenders as $key => $label)
        @include('partials.section-anchor', ['anchor' => $key, 'menu' => 'gender_menu'])
        <h2 class="unstyled uq-h2">
            {{ $label }}
            <span class="uq-count">{{ count($hmallProductList[$key]) }} 件</span>
        </h2>
        <div class="ts doubling link cards four">
            @foreach ($hmallProductList[$key] as $hmallProduct)
                @include('hmall-products.card', ['hmallProduct' => $hmallProduct])
            @endforeach
        </div>
    @endforeach
@endif
