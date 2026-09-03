@php
    $genders = ['men' => '男裝', 'women' => '女裝', 'kids' => '童裝', 'baby' => '嬰幼兒'];
    // 篩選過後常常只剩一兩種性別有商品，空的入口與空的區段都不該佔版面
    $available = collect($genders)->filter(fn($label, $key) => count($hmallProductList[$key]) > 0);
@endphp

@if ($available->isEmpty())
    @include('partials.empty-state', [
        'icon' => 'search faded',
        'title' => '沒有符合的商品',
        'hint' => '試試看少選幾個條件',
    ])
@else
    {{-- 性別是這一頁的章節，選單用跟商品頁、分類總覽同一個 partial --}}
    @include('partials.section-menu', [
        'id' => 'gender_menu',
        'items' => $available
            ->map(fn($label, $key) => [
                'anchor' => $key,
                'label' => $label,
                'count' => count($hmallProductList[$key]),
            ])
            ->values()
            ->all(),
    ])

    <div class="ts active basic horizontally fitted tab segment">
        @foreach ($available as $key => $label)
            @include('partials.section-anchor', ['anchor' => $key, 'menu' => 'gender_menu'])
            <h2 class="ts large header">
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
