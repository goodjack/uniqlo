@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')
@inject('outfitStatsPresenter', 'App\Presenters\OutfitStatsPresenter')
@extends('layouts.master')

@php
    $shareText = $hmallProductPresenter->getShareText($hmallProduct);
    $shareTextEncode = rawurlencode($shareText);

    $currentUrl = url()->current();
    $shareUrl = [
        'facebook' => rawurlencode($currentUrl . '?utm_source=uqs&utm_medium=fb&utm_campaign=share'),
        'twitter' => rawurlencode($currentUrl . '?utm_source=uqs&utm_medium=twtr&utm_campaign=share'),
        'line' => rawurlencode($currentUrl . '?utm_source=uqs&utm_medium=line&utm_campaign=share'),
        'threads' => rawurlencode($currentUrl . '?utm_source=uqs&utm_medium=threads&utm_campaign=share'),
        'webShare' => $currentUrl . '?utm_source=uqs&utm_medium=webshare&utm_campaign=share',
    ];

    $colorNums = json_decode($hmallProduct->color_nums, true);

    $adsenseClientId = config('app.adsense.client_id');
    $adsenseSlotId = config('app.adsense.slot_id');

    $productName = $hmallProductPresenter->getFullName($hmallProduct);

    // 只走一條分類路徑（CategoryService::getPrimaryCategory()）；掛不到分類就不硬湊
    $crumbs = array_merge(
        [\App\Support\Breadcrumb::home()],
        $categoryTrail->isEmpty() ? [] : [\App\Support\Breadcrumb::link('categories')],
        $categoryTrail
            ->map(
                fn($crumb) => $crumb->level === \App\Enums\CategoryLevel::Top
                    ? \App\Support\Breadcrumb::text("{$crumb->brand->value} {$crumb->name}")
                    : [
                        'label' => $crumb->name,
                        'url' => \App\Support\Url::category($crumb->brand, $crumb->code),
                    ],
            )
            ->all(),
        [\App\Support\Breadcrumb::text($productName)],
    );

    $productTags = $hmallProductPresenter->getProductTags($hmallProduct, true);

    $detail = $hmallProduct->detail;
    $mainImages = $hmallProductPresenter->getMainImages($hmallProduct);
    $features = $detail?->features ?: [];
    // 第一張主圖放上方，其餘都放進「商品實照」：台灣影片 → 日本影片 → 台灣圖 → 日本圖，日本版的每張標「日本版」
    $extraImages = array_slice($mainImages, 1);
    $officialVideos = $hmallProductPresenter->getVideos($hmallProduct);
    $japanMainImages = optional($japanProduct)->main_images ?? [];
    $japanSubImages = optional($japanProduct)->sub_images ?? [];
    $japanImages = [
        ...array_map(fn($image, $i) => [$image, '日本版商品穿搭照 ' . ($i + 1)], $japanMainImages, array_keys($japanMainImages)),
        ...array_map(fn($image, $i) => [$image, '日本版商品實照 ' . ($i + 1)], $japanSubImages, array_keys($japanSubImages)),
    ];
    $japanVideos = optional($japanProduct)->sub_videos ?? [];
    $materialCare = $detail?->material_care ?: [];

    // 只列有值的欄位。「適用對象」不沿用官方的「適穿」：值是男裝、女裝這種對象
    $productFacts = array_filter([
        '適用對象' => $hmallProduct->sex,
        '季節' => $hmallProduct->season,
        '產地' => $detail?->origin_place,
        '網路商店編號' => $hmallProduct->product_code,
    ], fn($value) => filled($value));
    $specLabels = [
        ...array_map(fn($section) => $hmallProductPresenter->getMaterialCareLabel($section['title']), $materialCare),
        ...array_keys($productFacts),
    ];

    // 不用 getDescription()：它文末接的網路商店編號，下面的商品規格已經有了
    $descriptionHtml = $hmallProductPresenter->getDescriptionBody($hmallProduct->instruction);

    $outfitCards = $outfitStats ? $outfitStatsPresenter->cards($hmallProduct, $outfitStats) : [];
    $outfitSizeTable = $outfitStats ? $outfitStatsPresenter->sizeTable($outfitStats) : null;

    $collapseAfter = ['mobile' => 2, 'desktop' => 8];
    $collapsible = fn (int $count, string $on) => $count > $collapseAfter[$on]
        ? new \Illuminate\Support\HtmlString("data-collapse-on=\"{$on}\" data-collapse-after=\"{$collapseAfter[$on]}\"")
        : '';
    $photoCount = count($officialVideos) + count($japanVideos) + count($extraImages) + count($colorNums ?? []) + count($japanImages);

    // 每一段有沒有內容。章節選單與下面各段的 @if 共用這一份，選單才不會指到不存在的段落
    $shown = [
        'photos' => (bool) ($officialVideos || $japanVideos || $extraImages || $colorNums || $japanImages),
        'features' => (bool) $features,
        'outfit-stats' => $outfitCards !== [] || $outfitSizeTable !== null,
        'style-hints' => $styleHints->isNotEmpty(),
        'official-styles' => $styles->isNotEmpty(),
        'social' => $socialPosts->isNotEmpty(),
        'commonly-styled' => $commonlyStyledHmallProducts->isNotEmpty(),
        'specs' => $specLabels !== [],
        'related' => $relatedHmallProducts->isNotEmpty() || $categories->isNotEmpty() || $relatedProducts->isNotEmpty(),
        'price-history' => true,
        'japan' => isset($japanProduct),
    ];
    $shown['styles'] = $shown['style-hints'] || $shown['official-styles'] || $shown['outfit-stats'];

    // 章節選單最多 7 項、順序跟內文一致；經常搭配與延伸商品往下逛自然會看到，不列
    $sections = collect([
        'features' => '特點',
        'photos' => '實照',
        'social' => '社群熱推',
        'styles' => '穿搭',
        'specs' => '規格',
        'price-history' => '歷史價格',
        'japan' => '日本版',
    ])
        ->filter(fn($label, $anchor) => $shown[$anchor])
        ->map(fn($label, $anchor) => ['anchor' => $anchor, 'label' => $label])
        ->values()
        ->all();
@endphp

@section('title', $hmallProductPresenter->getFullNameWithCode($hmallProduct))

@section('og-type', 'product')

@section('json-ld')
    @php
        $productJsonLd = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'sku' => $hmallProduct->product_code,
            'productID' => $hmallProduct->code,
            'model' => $hmallProduct->code,
            'name' => $productName,
            'description' => $hmallProductPresenter->getSocialMediaDescription($hmallProduct),
            'image' => array_column($mainImages, 'large'),
            'itemCondition' => 'https://schema.org/NewCondition',
            'brand' => ['@type' => 'Brand', 'name' => $hmallProduct->brand],
            // 價格區間刻意標歷史最低與最高價，跟頁面上顯示的一致
            'offers' => [
                '@type' => 'AggregateOffer',
                'lowPrice' => $hmallProduct->lowest_record_price,
                'highPrice' => $hmallProduct->highest_record_price,
                'priceCurrency' => 'TWD',
                'availability' => $hmallProductPresenter->getProductAvailabilityForJsonLd($hmallProduct),
                'itemCondition' => 'https://schema.org/NewCondition',
                'url' => $currentUrl,
                'seller' => ['@type' => 'Organization', 'name' => $hmallProduct->brand],
            ],
        ];

        if ($hmallProduct->evaluation_count > 0 && ! empty($hmallProduct->score)) {
            $productJsonLd['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $hmallProduct->score,
                'ratingCount' => $hmallProduct->evaluation_count,
            ];
        }
    @endphp
    @include('partials.json-ld', ['data' => $productJsonLd])
@endsection

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    <meta name="description" content="{{ $hmallProductPresenter->getSocialMediaDescription($hmallProduct) }}" />
    <meta property="og:url" content="{{ $currentUrl }}" />
    <meta property="og:description" content="{{ $hmallProductPresenter->getSocialMediaDescription($hmallProduct) }}" />
    <meta property="og:image" content="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:creator" content="@littlegoodjack" />
    <meta name="twitter:description" content="{{ $hmallProductPresenter->getSocialMediaDescription($hmallProduct) }}" />
    <meta name="twitter:image" content="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}" />
    <meta name="share:text" content="{{ $shareText }}" />
    <meta name="share:url" content="{{ $shareUrl['webShare'] }}" />
@endsection

@section('css')
    @include('partials.font-awesome')
    <style>
        .ts.card .overlapped.content.color-header {
            top: unset;
            height: unset;
            bottom: 0;
        }

        /* 分享 icon 平常的顏色：master 的 #a0aec0 在白底不到 3:1 */
        #facebook {
            color: var(--uq-muted);
        }

        #facebook:hover {
            background: #fff !important;
            color: #1877f2 !important;
        }

        #facebook:active {
            color: #145cbd !important;
        }

        #twitter {
            color: var(--uq-muted);
        }

        #twitter:hover {
            background: #fff !important;
            color: #0f1419 !important;
        }

        #twitter:active {
            color: #000 !important;
        }

        #line {
            color: var(--uq-muted);
        }

        /* LINE 品牌綠在白底不到 3:1，加深一階 */
        #line:hover {
            background: #fff !important;
            color: #05a52f !important;
        }

        #line:active {
            color: #048a27 !important;
        }

        #threads {
            color: var(--uq-muted);
        }

        #threads:hover {
            background: #fff !important;
            color: #101010 !important;
        }

        #threads:active {
            color: #000 !important;
        }

        .ts.button.coming-soon.positive {
            border-color: #50723C;
            background: #50723C;
        }

        .ts.button.coming-soon.positive:not(.visible):not(.hidden):not(.active):not(:active):not(.opinion):hover {
            background: #4B6B38;
        }

        .ts.button.coming-soon.positive:active:not(.opinion) {
            background: #415E31;
        }

        .ts.button.online-special.positive {
            border-color: #F29E18;
            background: #F29E18;
        }

        .ts.button.online-special.positive:not(.visible):not(.hidden):not(.active):not(:active):not(.opinion):hover {
            background: #D4880C;
        }

        .ts.button.online-special.positive:active:not(.opinion) {
            background: #AE6F0A;
        }

        /* 說明是這一頁的正文，16px 同 master */
        #comment {
            font-size: 1.14286rem;
            line-height: 1.625;
        }

        /* 桌機固定說明框高度（同 master），價格與按鈕那一列才會穩定落在圖片底部附近 */
        @media (min-width: 991px) {
            #comment {
                height: 400px;
                overflow: auto;
            }
        }
    </style>
@endsection

@section('content')
    <div class="ts basic very padded horizontally fitted fluid segment" id="product_hero">
        <div class="ts container">
            @include('partials.breadcrumb', ['crumbs' => $crumbs])
        </div>
        <div class="ts container relaxed grid">
            <div class="seven wide large screen eight wide computer sixteen wide tablet sixteen wide mobile column">
                <div class="ts fluid container">
                    {{-- 放大圖的說明同 x-image-card：轉義過的 template，由 data-description 指過去 --}}
                    <a class="ts centered image" href="{{ $mainImages[0]['large'] }}"
                        rel="nofollow noopener" data-lightbox="image"
                        data-description=".uq-caption-hero" data-type="image">
                        <x-lazy-load-image class="ts centered fluid image" :priority="true"
                            src="{{ $mainImages[0]['src'] }}"
                            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
                        <template class="uq-caption-hero">{{ $productName }}</template>
                    </a>
                </div>
            </div>
            <div class="nine wide large screen eight wide computer sixteen wide tablet sixteen wide mobile column">
                <div class="ts fluid very narrow container grid">
                    <div class="sixteen wide column">
                        <h1 class="ts dividing big header">
                            {{ $productName }}
                            <div class="sub header">
                                {{ $hmallProduct->brand }} 商品編號 {{ $hmallProduct->code }}
                                {!! $hmallProductPresenter->getRatingForProductShow($hmallProduct) !!}
                                <div class="uq-share-links">
                                    <div class="ts buttons">
                                        <a id="facebook" class="ts link button"
                                            href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl['facebook'] }}&quote={{ $shareTextEncode }}"
                                            target="_blank" rel="nofollow noopener" aria-label="分享到 Facebook">
                                            <i class="fa-brands fa-facebook" aria-hidden="true"></i>
                                        </a>
                                        <a id="twitter" class="ts link button"
                                            href="https://twitter.com/intent/tweet/?text={{ $shareTextEncode }}&url={{ $shareUrl['twitter'] }}"
                                            target="_blank" rel="nofollow noopener" aria-label="分享到 X">
                                            <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                                        </a>
                                        <a id="line" class="ts link button"
                                            href="https://social-plugins.line.me/lineit/share?text={{ $shareTextEncode }}&url={{ $shareUrl['line'] }}"
                                            target="_blank" rel="nofollow noopener" aria-label="分享到 LINE">
                                            <i class="fa-brands fa-line" aria-hidden="true"></i>
                                        </a>
                                        <a id="threads" class="ts link button"
                                            href="https://www.threads.com/intent/post?text={{ $shareTextEncode }}&url={{ $shareUrl['threads'] }}"
                                            target="_blank" rel="nofollow noopener" aria-label="分享到 Threads">
                                            <i class="fa-brands fa-threads" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </h1>
                    </div>
                    <div class="sixteen wide column">
                        <div class="ts basic fitted segment">
                            {{-- 有對應清單頁的標籤做成連結 --}}
                            @foreach ($productTags as $tag)
                                @if ($tag['url'])
                                    <a class="ts horizontal basic circular label" href="{{ $tag['url'] }}">
                                        <span style="color: {{ $tag['color'] }};"><i
                                                class="{{ $tag['icon'] }} icon"></i>{{ $tag['text'] }}</span>
                                    </a>
                                @else
                                    <div class="ts horizontal basic circular label">
                                        <span style="color: {{ $tag['color'] }};"><i
                                                class="{{ $tag['icon'] }} icon"></i>{{ $tag['text'] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="sixteen wide column">
                        <div class="ts basic horizontally fitted segment" id="comment">
                            <p>{!! $descriptionHtml !!}</p>
                        </div>
                    </div>
                </div>
                <div class="ts divider"></div>
                <div class="ts grid">
                    <div class="four wide column">
                        <h2>${{ $hmallProduct->price }}</h2>
                    </div>
                    <div class="twelve wide column">
                        <div id="uniqlo-column" data-keeps-clear>
                            <div class="ts right floated separated stackable buttons">
                                @if ($hmallProduct->brand === 'GU')
                                    <a class="ts info right labeled icon button"
                                        href="{{ \App\Support\Url::officialTaiwanProduct(\App\Enums\Brand::Gu, $hmallProduct->product_code) }}"
                                        target="_blank" rel="nofollow noopener" aria-label="GU" data-analytics-official="hero">前往 GU 官網<i
                                            class="external icon"></i></a>
                                @else
                                    <a class="ts negative right labeled icon button"
                                        href="{{ \App\Support\Url::officialTaiwanProduct(\App\Enums\Brand::Uniqlo, $hmallProduct->product_code) }}"
                                        target="_blank" rel="nofollow noopener" aria-label="UNIQLO" data-analytics-official="hero">前往 UNIQLO 官網<i
                                            class="external icon"></i></a>
                                @endif
                                {{-- 文字固定「收藏」，狀態只靠 aria-pressed：切換按鈕的名稱不隨狀態變 --}}
                                <button type="button" class="ts basic button" data-favorite-button
                                    data-brand="{{ $hmallProduct->brand }}"
                                    data-product-code="{{ $hmallProduct->product_code }}"
                                    data-analytics-item="{{ json_encode($hmallProductPresenter->getAnalyticsItem($hmallProduct), JSON_UNESCAPED_UNICODE) }}"
                                    aria-pressed="false"><i
                                        class="heart outline icon"></i><span class="label">收藏</span></button>
                                <button type="button" class="ts basic button" id="share" hidden><i
                                        class="fa-solid fa-share-nodes" aria-hidden="true"></i>分享</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.section-menu', ['id' => 'product_menu', 'items' => $sections, 'revealAfter' => 'product_hero'])

    {{-- 各段同一種白底、不分色塊，靠段標題與留白分段（照 UNIQLO、GU 官網）。
         段標題的 data-section-view 是 GA 的段落曝光，名稱對到章節錨點 --}}
    @if ($shown['features'])
        <div class="ts basic padded horizontally fitted fluid segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'features', 'menu' => 'product_menu'])
                <x-section-header section="features">商品特點</x-section-header>
                {{-- 跟商品實照同樣大小：電腦一列四張、手機兩張，說明放圖下方；點圖放大看圖裡的字 --}}
                <div class="ts doubling four cards">
                    @foreach ($features as $feature)
                        <div class="ts card">
                            @if ($feature['image'])
                                <a class="image" href="{{ $hmallProductPresenter->getOfficialAssetUrl($hmallProduct, $feature['image']) }}"
                                    rel="nofollow noopener" data-lightbox="features" data-type="image"
                                    data-description=".uq-caption-feature-{{ $loop->index }}">
                                    <x-lazy-load-image :src="$hmallProductPresenter->getOfficialAssetUrl($hmallProduct, $feature['image'])"
                                        :alt="$feature['text'] ?: '商品特點 ' . $loop->iteration" width="1000" height="1000" />
                                    <template class="uq-caption-feature-{{ $loop->index }}">{{ $feature['text'] ?: '商品特點 ' . $loop->iteration }}</template>
                                </a>
                            @endif
                            @if ($feature['text'])
                                <div class="content">
                                    <div class="description">{{ $feature['text'] }}</div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($shown['photos'])
        <div class="ts basic padded horizontally fitted fluid segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'photos', 'menu' => 'product_menu'])
                <x-section-header section="photos">商品實照</x-section-header>
                {{-- 影片排在最前面，跟圖同一排滑動；日本版混在一起、右上角標來源（同 StyleHint 的多國穿搭） --}}
                <div class="ts doubling four flatted cards uq-strip" {{ $collapsible($photoCount, 'desktop') }}>
                    @foreach ($officialVideos as $video)
                        @include('hmall-products.partials.video-card', ['video' => $video])
                    @endforeach
                    @foreach ($japanVideos as $video)
                        @include('hmall-products.partials.video-card', ['video' => $video, 'country' => 'jp'])
                    @endforeach
                    @foreach ($extraImages as $key => $image)
                        <x-image-card imageUrl="{{ $image['src'] }}" largeImageUrl="{{ $image['large'] }}"
                            link="" alt="官網商品圖 {{ $key + 2 }}" width="1200" height="1600" />
                    @endforeach
                    @foreach ($colorNums ?? [] as $key => $colorNum)
                        <x-image-card imageUrl="{{ $hmallProductPresenter->getSkuPic($hmallProduct, $colorNum) }}"
                            largeImageUrl="{{ $hmallProductPresenter->getSkuPic($hmallProduct, $colorNum) }}"
                            link="" alt="商品實照 {{ $key + 1 }}" width="1200" height="1600" />
                    @endforeach
                    @foreach ($japanImages as [$image, $alt])
                        <x-image-card imageUrl="{{ $image }}" largeImageUrl="{{ $image }}" country="jp"
                            link="" alt="{{ $alt }}" width="1200" height="1600" />
                    @endforeach
                </div>
                @includeWhen($collapsible($photoCount, 'desktop'), 'partials.expand-button', ['on' => 'desktop', 'label' => "顯示全部 {$photoCount} 張"])
            </div>
        </div>
    @endif

    {{-- 社群熱推緊接商品實照：網紅實穿是看真人穿起來的第一站 --}}
    @if ($shown['social'])
        <div class="ts basic padded horizontally fitted fluid segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'social', 'menu' => 'product_menu'])
                <x-section-header section="social" action-icon="play" :action-label="'逛全部 ' . $socialPosts->count() . ' 則'" :action-data="['data-social-all' => '']">社群熱推</x-section-header>
                @include('hmall-products.partials.social-posts', ['socialPosts' => $socialPosts])
            </div>
        </div>
    @endif

    {{-- 穿搭一整段：網友、官方，最後用穿搭統計收尾；章節選單的「穿搭」跳到這段開頭 --}}
    @if ($shown['styles'])
        <div class="ts basic padded horizontally fitted fluid segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'styles', 'menu' => 'product_menu'])

                @if ($shown['style-hints'])
                    <x-section-header id="style-hints" section="style-hints" :sub="'共 ' . $styleHintCount . ' 張'"
                        action-label="看更多" action-icon="camera retro" :action-href="$hmallProductPresenter->getStyleHintsRoute($hmallProduct)">StyleHint 穿搭靈感</x-section-header>
                    <div class="ts doubling four flatted cards uq-strip">
                        @foreach ($styleHints as $key => $styleHint)
                            <x-image-card link="{{ $styleHint->official_site_url }}" imageUrl="{{ $styleHint->image_url }}"
                                largeImageUrl="{{ $styleHint->large_image_url }}" country="{{ $styleHint->country }}"
                                :alt="'StyleHint 網友穿搭靈感 ' . ($key + 1) . ' (' . $styleHint->user_name . ')'" width="720"
                                height="960" album="style-hints" />
                        @endforeach
                        @if ($styleHintCount > count($styleHints))
                            <a class="ts borderless card uq-strip-more" href="{{ $hmallProductPresenter->getStyleHintsRoute($hmallProduct) }}">
                                看全部 {{ $styleHintCount }} 張
                            </a>
                        @endif
                    </div>
                    @if ($shown['official-styles'] || $shown['outfit-stats'])
                        <div class="ts hidden section divider"></div>
                    @endif
                @endif

                @if ($shown['official-styles'])
                    <x-section-header id="official-styles" section="official-styles">Official Styling 官方精選穿搭</x-section-header>
                    <div class="ts doubling four flatted cards uq-strip" {{ $collapsible($styles->count(), 'desktop') }}>
                        @foreach ($styles as $key => $style)
                            <x-image-card link="{{ $style->detail_url }}" imageUrl="{{ $style->image_url }}"
                                largeImageUrl="{{ $style->large_image_url }}"
                                alt="Official Styling 官方精選穿搭 {{ $key + 1 }}" width="720" height="960" album="styles" />
                        @endforeach
                    </div>
                    @includeWhen($collapsible($styles->count(), 'desktop'), 'partials.expand-button', ['on' => 'desktop', 'label' => "顯示全部 {$styles->count()} 張"])
                    @if ($shown['outfit-stats'])
                        <div class="ts hidden section divider"></div>
                    @endif
                @endif

                @if ($shown['outfit-stats'])
                    @include('hmall-products.partials.outfit-stats', ['stats' => $outfitStats, 'cards' => $outfitCards, 'sizeTable' => $outfitSizeTable])
                @endif
            </div>
        </div>
    @endif

    @if ($shown['commonly-styled'])
        <div class="ts basic padded horizontally fitted fluid segment">
            <div class="ts container">
                <x-section-header id="commonly-styled" section="commonly-styled">經常搭配商品</x-section-header>
                <div class="ts doubling cards six uq-product-cards" data-analytics-list="product_commonly_styled" {{ $collapsible($commonlyStyledHmallProducts->count(), 'mobile') }}>
                    @each('hmall-products.simple-card', $commonlyStyledHmallProducts, 'hmallProduct')
                </div>
                @includeWhen($collapsible($commonlyStyledHmallProducts->count(), 'mobile'), 'partials.expand-button', ['on' => 'mobile', 'label' => '展開其餘 ' . ($commonlyStyledHmallProducts->count() - $collapseAfter['mobile']) . ' 件'])
            </div>
        </div>
    @endif

    {{-- 規格是要查的人才看，預設收合（官網也這樣）；標題露出有哪幾項 --}}
    @if ($shown['specs'])
        <div class="ts basic padded horizontally fitted fluid segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'specs', 'menu' => 'product_menu'])
                <details class="ts accordion uq-specs" data-analytics-details="specs">
                    <summary>
                        <x-section-header section="specs" :sub="implode('、', array_slice($specLabels, 0, 2)) . (count($specLabels) > 2 ? '等 ' . count($specLabels) . ' 項' : '')">
                            <x-slot:icon><i class="dropdown icon" aria-hidden="true"></i></x-slot:icon>商品規格
                        </x-section-header>
                    </summary>
                    <div class="content">
                        <table class="ts very basic table uq-facts">
                            <tbody>
                                @foreach ($materialCare as $section)
                                    <tr>
                                        <td class="collapsing">{{ $hmallProductPresenter->getMaterialCareLabel($section['title']) }}</td>
                                        <td>
                                            <div class="ts list">
                                                @foreach ($hmallProductPresenter->getMaterialCareLines($section['content']) as $line)
                                                    <div class="item">{{ $line }}</div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                @foreach ($productFacts as $label => $value)
                                    <tr>
                                        <td class="collapsing">{{ $label }}</td>
                                        <td>{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    @endif

    @if ($shown['related'])
        <div class="ts basic padded horizontally fitted fluid segment">
            <div class="ts container">
                <x-section-header id="related" section="related">延伸商品</x-section-header>
                @if ($categories->isNotEmpty())
                    {{-- 商品同時掛在男裝、女裝、企劃等幾棵樹下，是平行的入口，所以用中點清單、不用麵包屑 --}}
                    <div class="ts horizontal middoted list uq-categories-line" aria-label="所屬分類">
                        @foreach ($categories as $category)
                            <a class="item"
                                href="{{ \App\Support\Url::category($category->brand, $category->code) }}">{{ $category->name }}</a>
                        @endforeach
                    </div>
                    <div class="ts hidden divider"></div>
                @endif
                @if ($relatedHmallProducts->isNotEmpty())
                    <div class="ts doubling cards six uq-product-cards" data-analytics-list="product_related" {{ $collapsible($relatedHmallProducts->count(), 'mobile') }}>
                        @each('hmall-products.card', $relatedHmallProducts, 'hmallProduct')
                    </div>
                    @includeWhen($collapsible($relatedHmallProducts->count(), 'mobile'), 'partials.expand-button', ['on' => 'mobile', 'label' => '展開其餘 ' . ($relatedHmallProducts->count() - $collapseAfter['mobile']) . ' 件'])
                @endif
                @if ($relatedProducts->isNotEmpty())
                    <div class="ts hidden section divider"></div>
                    <h3 class="ts medium header">舊系統商品</h3>
                    <div class="ts doubling cards six uq-product-cards uq-text-cards">
                        @each('products.card', $relatedProducts, 'product')
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- 歷史價格與日本版固定排最後：從選單跳過來的人才會翻到頁尾 --}}
    <div class="ts basic padded horizontally fitted fluid segment">
        <div class="ts container">
            @include('partials.section-anchor', ['anchor' => 'price-history', 'menu' => 'product_menu'])
            <x-section-header section="price-history">歷史價格</x-section-header>
            <div class="ts fluid container grid">
                <div class="four wide computer sixteen wide tablet sixteen wide mobile column">
                    <div class="ts grid" data-section-view="price-history" data-section-exposure="content">
                        <div class="sixteen wide computer eight wide tablet eight wide mobile column">
                            <div class="ts card">
                                <div class="center aligned content">
                                    <div class="ts medium statistic">
                                        <div class="value">{{ (int) $hmallProduct->highest_record_price }}</div>
                                        <div class="label">歷史高價</div>
                                    </div>
                                </div>
                                <div class="symbol">
                                    <i class="arrow up icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="sixteen wide computer eight wide tablet eight wide mobile column">
                            <div class="ts card">
                                <div class="center aligned content">
                                    <div class="ts medium statistic">
                                        <div class="value">{{ (int) $hmallProduct->lowest_record_price }}</div>
                                        <div class="label">歷史低價</div>
                                    </div>
                                </div>
                                <div class="symbol">
                                    <i class="arrow down icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ts hidden divider"></div>
                </div>
                <div class="twelve wide computer sixteen wide tablet sixteen wide mobile column">
                    <div class="ts flatted card">
                        <div class="image">
                            <canvas id="priceChart" aria-label="價格走勢"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            {{-- 放在價格走勢下方：從章節選單跳過來的人看完圖表就會看到，也不擋在內容前面 --}}
            @if (!empty($adsenseClientId) && !empty($adsenseSlotId))
                <div class="uq-ad">
                    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $adsenseClientId }}"
                        crossorigin="anonymous"></script>
                    <ins class="adsbygoogle" style="display:block; text-align:center;" data-ad-layout="in-article"
                        data-ad-format="fluid" data-ad-client="{{ $adsenseClientId }}" data-ad-slot="{{ $adsenseSlotId }}">
                    </ins>
                    <script>
                        (adsbygoogle = window.adsbygoogle || []).push({});
                    </script>
                </div>
            @endif
        </div>
    </div>

    @if ($shown['japan'])
        <div class="ts basic padded horizontally fitted fluid segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'japan', 'menu' => 'product_menu'])
                <x-section-header section="japan">日本版商品資訊</x-section-header>
                <div class="ts items">
                    <div class="item">
                        <div class="ts tiny image">
                            <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
                                alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
                        </div>
                        <div class="content">
                            <a class="header">{{ $japanProduct->name }}</a>
                            <div class="meta">
                                <span>{{ $japanProduct->brand }} 日本商品編號 {{ $japanProduct->l1Id }}
                                    ({{ $japanProduct->product_id }})</span>
                            </div>
                            <div class="extra">
                                @if ($japanProduct->is_stockout)
                                    <div class="ts circular horizontal label"><i class="archive icon"></i>已售罄</div>
                                @else
                                    <div class="ts circular horizontal label"><i class="check icon"></i>発売中</div>
                                @endif
                                資訊日期：{!! $japanProduct->updated_at->format('Y/m/d') !!}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ts doubling four column grid" data-section-view="japan" data-section-exposure="content">
                    <div class="column">
                        <div class="ts card">
                            <div class="center aligned content">
                                <div class="ts small statistic">
                                    <div class="value">{!! $hmallProductPresenter->getJapanRating($hmallProduct, true) !!}</div>
                                    <div class="label">日本評價</div>
                                </div>
                            </div>
                            <div class="symbol">
                                <i class="comments icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="ts card">
                            <div class="center aligned content">
                                <div class="ts small statistic">
                                    <div class="value">¥{{ (int) collect($japanProduct->prices)->first() }}</div>
                                    <div class="label">當前價格</div>
                                </div>
                            </div>
                            <div class="symbol">
                                <i class="yen icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="ts card">
                            <div class="center aligned content">
                                <div class="ts small statistic">
                                    <div class="value">¥{{ (int) $japanProduct->highest_record_price }}</div>
                                    <div class="label">歷史高價</div>
                                </div>
                            </div>
                            <div class="symbol">
                                <i class="arrow up icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="ts card">
                            <div class="center aligned content">
                                <div class="ts small statistic">
                                    <div class="value">¥{{ (int) $japanProduct->lowest_record_price }}</div>
                                    <div class="label">歷史低價</div>
                                </div>
                            </div>
                            <div class="symbol">
                                <i class="arrow down icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="right floated column">
                        @php
                            $japanBrand = $japanProduct->brand === 'GU' ? \App\Enums\Brand::Gu : \App\Enums\Brand::Uniqlo;
                            $japanUrl = collect($japanProduct->prices)->count() >= 2
                                ? \App\Support\Url::officialJapanSearch($japanBrand, $japanProduct->l1Id)
                                : \App\Support\Url::officialJapanProduct($japanBrand, $japanProduct->product_id);
                        @endphp
                        <a class="ts right floated tiny basic {{ $japanProduct->brand === 'GU' ? 'info' : 'negative' }} right labeled icon button"
                            href="{{ $japanUrl }}" target="_blank" rel="nofollow noopener"
                            aria-label="{{ $japanProduct->brand }}" data-analytics-official="japan">前往日本 {{ $japanProduct->brand }} 官網<i
                                class="external icon"></i></a>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('javascript')
    @include('partials.photo-lightbox')
    @if ($shown['social'])
        <script src="{{ asset('js/social-posts.js') }}?v={{ filemtime(public_path('js/social-posts.js')) }}" defer></script>
    @endif
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.bundle.min.js"
        integrity="sha512-SuxO9djzjML6b9w9/I07IWnLnQhgyYVSpHZx0JV97kGBfTIsUYlWflyuW4ypnvhBrslz1yJ3R+S14fdCWmSmSA=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="{{ asset('js/product-page.js') }}?v={{ filemtime(public_path('js/product-page.js')) }}"></script>

    <script>
        'use strict';

        Chart.defaults.LineWithLine = Chart.defaults.line;
        Chart.controllers.LineWithLine = Chart.controllers.line.extend({
            draw: function(ease) {
                Chart.controllers.line.prototype.draw.call(this, ease);
                if (this.chart.tooltip._active && this.chart.tooltip._active.length) {
                    let activePoint = this.chart.tooltip._active[0],
                        ctx = this.chart.ctx,
                        x = activePoint.tooltipPosition().x,
                        y = activePoint.tooltipPosition().y,
                        topY = this.chart.scales['y-axis-0'].top,
                        bottomY = this.chart.scales['y-axis-0'].bottom;

                    ctx.save();
                    ctx.beginPath();
                    ctx.moveTo(x, topY - 20);
                    ctx.lineTo(x, bottomY);
                    ctx.lineWidth = 35;
                    ctx.strokeStyle = 'rgba(206, 94, 87, 0.05)';
                    ctx.stroke();
                    ctx.restore();

                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(x, y, 4, 0, 2 * Math.PI);
                    ctx.fillStyle = 'rgba(206, 94, 87, 1.0)';
                    ctx.fill()
                    ctx.stroke();
                    ctx.restore();
                }
            }
        });

        let ctx = document.getElementById("priceChart");
        let pointBackgroundColor = [];
        let pointRadius = [];
        let priceChart = new Chart(ctx, {
            type: 'LineWithLine',
            data: {
                datasets: [{
                    label: '價格',
                    data: {!! $hmallProductPresenter->getPriceChartData($hmallPriceHistories) !!},
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    borderColor: 'rgba(206, 94, 87, 1.0)',
                    borderWidth: 2,
                    cubicInterpolationMode: 'default',
                    steppedLine: true,
                    pointBackgroundColor: pointBackgroundColor,
                    pointRadius: pointRadius,
                    pointHoverBorderWidth: 13,
                    pointHoverBorderColor: 'rgba(206, 94, 87, 0.3)'
                }],
                multiBuyData: []
            },
            options: {
                title: {
                    display: false,
                },
                legend: {
                    display: false
                },
                hover: {
                    mode: 'index',
                    intersect: false,
                    axis: 'x',
                    animationDuration: 0
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    axis: 'x',
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    xPadding: 11,
                    yPadding: 8,
                    titleFontSize: 14,
                    bodyFontSize: 14,
                    footerFontColor: 'rgba(218, 133, 128, 1.0)',
                    displayColors: false,
                    callbacks: {
                        label: function(tooltipItem, data) {
                            return data.datasets[tooltipItem.datasetIndex].label + "：NT$" + tooltipItem.yLabel;
                        },
                        footer: function(tooltipItems, data) {
                            if (data.multiBuyData[tooltipItems[0].index] !== null) {
                                return data.multiBuyData[tooltipItems[0].index];
                            }
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        type: 'time',
                        distribution: 'linear',
                        time: {
                            tooltipFormat: 'MM/DD',
                            displayFormats: {
                                day: 'MM/DD'
                            }
                        },
                        gridLines: {
                            drawOnChartArea: false
                        }
                    }],
                    yAxes: [{
                        gridLines: {
                            color: 'rgba(206, 94, 87, 0.1)'
                        },
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });

        for (let i = 0; i < priceChart.data.datasets[0].data.length; i++) {
            if (priceChart.data.multiBuyData[i] === null) {
                pointBackgroundColor.push('rgba(206, 94, 87, 0.2)');
                pointRadius.push(0);
            } else {
                pointBackgroundColor.push('rgba(255, 255, 0, 1)');
                pointRadius.push(2);
            }
        }

        priceChart.update();

        async function webShare() {
            if (navigator.share === undefined) {
                return;
            }

            const title = document.title;
            const text = document.querySelector('meta[name="share:text"]').getAttribute('content');
            const url = document.querySelector('meta[name="share:url"]').getAttribute('content');

            try {
                await navigator.share({
                    title,
                    text,
                    url
                });
            } catch (error) {}
        }

        function onLoad() {
            if (navigator.share !== undefined) {
                document.getElementById('share').hidden = false;

                // 手機的系統分享選單已經涵蓋這幾個平台，同一件事不放兩組按鈕
                if (window.matchMedia('(pointer: coarse)').matches) {
                    document.querySelector('.uq-share-links').hidden = true;
                }
            }

            document.querySelector('#share').addEventListener('click', webShare);
        }

        window.addEventListener('load', onLoad);
    </script>
@endsection
