@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')
@extends('layouts.master')

@php
    $shareText = $hmallProductPresenter->getFullName($hmallProduct) . ' | UNIQLO 比價 | UQ 搜尋';
    $shareTextEncode = urlencode($shareText);

    $currentUrl = url()->current();
    $shareUrl = [
        'facebook' => urlencode($currentUrl . '?utm_source=uqs&utm_medium=fb&utm_campaign=share'),
        'twitter' => urlencode($currentUrl . '?utm_source=uqs&utm_medium=twtr&utm_campaign=share'),
        'line' => urlencode($currentUrl . '?utm_source=uqs&utm_medium=line&utm_campaign=share'),
        'webShare' => $currentUrl . '?utm_source=uqs&utm_medium=webshare&utm_campaign=share',
    ];

    $colorNums = json_decode($hmallProduct->color_nums, true);

    $adsenseClientId = config('app.adsense.client_id');
    $adsenseSlotId = config('app.adsense.slot_id');

    $productName = $hmallProductPresenter->getFullName($hmallProduct);

    /*
     * 麵包屑只走一條分類路徑，規則在 HmallProduct::primaryCategory()。
     * 商品掛不到任何大類或品項時就只剩首頁那一層，不硬湊一條假的路徑。
     */
    $crumbs = array_merge(
        [\App\Support\Breadcrumb::home()],
        $categoryTrail->isEmpty() ? [] : [\App\Support\Breadcrumb::link('categories')],
        $categoryTrail
            ->map(
                fn($crumb) => $crumb->level === \App\Enums\CategoryLevel::Top
                    ? \App\Support\Breadcrumb::text("{$crumb->brand->value} {$crumb->name}")
                    : [
                        'label' => $crumb->name,
                        'url' => route('categories.show', [
                            'brand' => $crumb->brand->slug(),
                            'code' => $crumb->code,
                        ]),
                    ],
            )
            ->all(),
        [\App\Support\Breadcrumb::text($productName)],
    );

    $productTagsHtml = trim($hmallProductPresenter->getHmallProductTag($hmallProduct));

    // 商品資訊小表：只列出真的有值的欄位，空的一列比沒有那一列還難讀
    $productFacts = array_filter([
        '網路商店編號' => $hmallProduct->product_code,
        '適穿' => $hmallProduct->sex,
        '季節' => $hmallProduct->season,
    ], fn($value) => filled($value));

    /*
     * 網路商店編號改由上面那張小表呈現，說明文末那一行就重複了，畫面上拿掉。
     * 只在這裡拿掉、不動 getDescription()：社群分享的描述與爬蟲判斷「說明是不是
     * 太短」都在讀它的回傳值，改了會一起變。
     */
    $descriptionHtml = preg_replace(
        '/(<br>)*網路商店編號：' . preg_quote($hmallProduct->product_code, '/') . '\s*$/u',
        '',
        $hmallProductPresenter->getDescription($hmallProduct),
    );

    /*
     * 章節選單的項目要跟底下真的渲染出來的區段一致，所以條件跟各區段的 @if 同一份。
     * 選單的字比標題短，橫向才排得下。
     */
    $sections = collect([
        ['anchor' => 'videos', 'label' => '商品影片', 'shown' => (bool) optional($japanProduct)->has_videos],
        [
            'anchor' => 'photos',
            'label' => '商品實照',
            'shown' => (bool) ($colorNums || optional($japanProduct)->main_images || optional($japanProduct)->sub_images),
        ],
        ['anchor' => 'styles', 'label' => '官方穿搭', 'shown' => $styles->isNotEmpty()],
        ['anchor' => 'style-hints', 'label' => '網友穿搭', 'shown' => $styleHints->isNotEmpty()],
        ['anchor' => 'commonly-styled', 'label' => '經常搭配', 'shown' => $commonlyStyledHmallProducts->isNotEmpty()],
        ['anchor' => 'related', 'label' => '延伸商品', 'shown' => $relatedHmallProducts->isNotEmpty()],
        ['anchor' => 'price-history', 'label' => '歷史價格', 'shown' => true],
        ['anchor' => 'japan', 'label' => '日本版資訊', 'shown' => isset($japanProduct)],
        ['anchor' => 'legacy', 'label' => '舊系統商品', 'shown' => $relatedProducts->isNotEmpty()],
    ])
        ->filter(fn($section) => $section['shown'])
        ->values()
        ->all();
@endphp

@section('title', $hmallProductPresenter->getFullName($hmallProduct))

@section('json-ld')
    <script type="application/ld+json">
        {
            "@context": "https://schema.org/",
            "@type": "Product",
            "sku": "{{ $hmallProduct->product_code }}",
            "productID": "{{ $hmallProduct->code }}",
            "model": "{{ $hmallProduct->code }}",
            "name": "{{ $hmallProductPresenter->getFullName($hmallProduct) }} | UNIQLO 比價 | UQ 搜尋",
            "description": {!! $hmallProductPresenter->getDescriptionForJsonLd($hmallProduct) !!},
            "image": [
                "{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
            ],
            "itemCondition": "http://schema.org/NewCondition",
            "brand": {
                "@type": "Brand",
                "name": "{{ $hmallProduct->brand }}"
            },
            "offers": {
                "@type": "AggregateOffer",
                "lowPrice": "{{ $hmallProduct->lowest_record_price }}",
                "highPrice": "{{ $hmallProduct->highest_record_price }}",
                "priceCurrency": "TWD",
                "priceValidUntil": "{{ $hmallProduct->updated_at->toDateString() }}",
                "availability": "{{ $hmallProductPresenter->getProductAvailabilityForJsonLd($hmallProduct) }}",
                "itemCondition": "http://schema.org/NewCondition",
                "url": "{{ $currentUrl }}",
                "seller": {
                    "@type": "Organization",
                    "name": "{{ $hmallProduct->brand }}"
                }
            }
            @if ($hmallProduct->evaluation_count > 0 && !empty($hmallProduct->score))
                , "aggregateRating": {
                    "ratingValue": "{{ $hmallProduct->score }}",
                    "ratingCount": "{{ $hmallProduct->evaluation_count }}"
                }
            @endif
        }
    </script>
@endsection

@section('metadata')
    <link rel="canonical" href="{{ $currentUrl }}" />
    <meta name="description" content="{{ $hmallProductPresenter->getSocialMediaDescription($hmallProduct) }}" />
    <meta property="og:type" content="og:product" />
    <meta property="og:title" content="{{ $hmallProductPresenter->getFullName($hmallProduct) }} | UQ 搜尋" />
    <meta property="og:url" content="{{ $currentUrl }}" />
    <meta property="og:description" content="{{ $hmallProductPresenter->getSocialMediaDescription($hmallProduct) }}" />
    <meta property="og:image" content="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:creator" content="@littlegoodjack" />
    <meta name="twitter:title" content="{{ $hmallProductPresenter->getFullName($hmallProduct) }} | UQ 搜尋" />
    <meta name="twitter:description" content="{{ $hmallProductPresenter->getSocialMediaDescription($hmallProduct) }}" />
    <meta name="twitter:image" content="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}" />
    <meta name="share:text" content="{{ $shareText }}" />
    <meta name="share:url" content="{{ $shareUrl['webShare'] }}" />
@endsection

@section('css')
    <style>
        .ts.card .overlapped.content.color-header {
            top: unset;
            height: unset;
            bottom: 0;
        }

        #facebook {
            color: #a0aec0;
        }

        #facebook:hover {
            color: #1877f2 !important;
        }

        #facebook:active {
            color: #145cbd !important;
        }

        #twitter {
            color: #a0aec0;
        }

        #twitter:hover {
            color: #1d95e0 !important;
        }

        #twitter:active {
            color: #0d7bbf !important;
        }

        #line {
            color: #a0aec0;
        }

        #line:hover {
            color: #06b833 !important;
        }

        #line:active {
            color: #05a52f !important;
        }

        #comment {
            font-size: 1.14286rem;
            line-height: 1.625;
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

        @media (min-width: 991px) {
            /*
             * 說明很長的時候要收起來，但價格與按鈕搬到上面之後，右欄常常比
             * 左邊那張圖短——寫死高度會在說明只有一兩行時撐出一大塊空白。
             */
            #comment {
                max-height: 400px;
                overflow: auto;
            }
        }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css"
        integrity="sha512-ZKX+BvQihRJPA8CROKBhDNvoc2aDMOdAlcm7TUQY+35XYtrd3yh95QOOhsPDQY9QnKE0Wqag9y38OIgEvb88cA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
@endsection

@section('content')
    <div class="ts very padded horizontally fitted attached fluid segment">
        <div class="ts container">
            @include('partials.breadcrumb', ['crumbs' => $crumbs])
        </div>
        <div class="ts container relaxed grid">
            <div class="seven wide large screen eight wide computer sixteen wide tablet sixteen wide mobile column">
                <div class="ts fluid container">
                    <a class="ts centered image" href="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
                        rel="nofollow noopener" data-lightbox="image"
                        data-title="{{ $hmallProductPresenter->getFullName($hmallProduct) }}">
                        <x-lazy-load-image class="ts centered image"
                            src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
                            alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}" />
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
                                &middot;
                                <div class="ts buttons">
                                    <a id="facebook" class="ts link button"
                                        href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl['facebook'] }}&quote={{ $shareTextEncode }}"
                                        target="_blank" rel="nofollow noopener" aria-label="Facebook">
                                        <i class="facebook icon"></i>
                                    </a>
                                    <a id="twitter" class="ts link button"
                                        href="https://twitter.com/intent/tweet/?text={{ $shareTextEncode }}&url={{ $shareUrl['twitter'] }}"
                                        target="_blank" rel="nofollow noopener" aria-label="Twitter">
                                        <i class="twitter icon"></i>
                                    </a>
                                    <a id="line" class="ts link button"
                                        href="https://social-plugins.line.me/lineit/share?text={{ $shareTextEncode }}&url={{ $shareUrl['line'] }}"
                                        target="_blank" rel="nofollow noopener" aria-label="Line">
                                        <i class="chat icon"></i>
                                    </a>
                                </div>
                            </div>
                        </h1>
                    </div>

                    {{-- 買，或等：這是使用者在商品頁的兩個終點動作，所以緊接在標題底下， --}}
                    {{-- 不再壓在右欄最下面等人捲過標籤、分類與整段商品說明才看得到。 --}}
                    <div class="sixteen wide column">
                        <div class="ts grid">
                            <div class="four wide column">
                                <h2>${{ $hmallProduct->price }}</h2>
                            </div>
                            <div class="twelve wide column">
                                <div id="uniqlo-column">
                            <div class="ts right floated separated stackable buttons">
                                @if ($hmallProduct->brand === 'GU')
                                    <a class="ts info right labeled icon button"
                                        href="https://www.gu-global.com/tw/zh_TW/product-detail.html?productCode={{ $hmallProduct->product_code }}"
                                        target="_blank" rel="nofollow noopener" aria-label="GU">前往 GU 官網<i
                                            class="external icon"></i></a>
                                @else
                                    <a class="ts negative right labeled icon button"
                                        href="https://www.uniqlo.com/tw/zh_TW/product-detail.html?productCode={{ $hmallProduct->product_code }}"
                                        target="_blank" rel="nofollow noopener" aria-label="UNIQLO">前往 UNIQLO 官網<i
                                            class="external icon"></i></a>
                                @endif
                                {{-- 買，或等：這是使用者在商品頁的兩個終點動作，所以伺服器端就渲染出來 --}}
                                {{-- 不給 aria-label：它會蓋掉看得到的「收藏」，而且切換狀態時不會跟著改， --}}
                                {{-- 讀螢幕的人會一直聽到 Favorite。可及名稱交給 .label 的文字，favorites.js 兩邊一起換。 --}}
                                <button class="ts basic button" data-favorite-button
                                    data-brand="{{ $hmallProduct->brand }}"
                                    data-product-code="{{ $hmallProduct->product_code }}"
                                    aria-pressed="false"><i class="heart outline icon"></i><span
                                        class="label">收藏</span></button>
                                <a class="ts basic button" id="share" target="_blank" rel="nofollow noopener"
                                    aria-label="Share" style="display: none;"><i class="share icon"></i>分享</a>
                            </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sixteen wide column">
                        <div class="ts divider"></div>
                    </div>

                    @if ($productTagsHtml !== '')
                        <div class="sixteen wide column">
                            <h3 class="ts small header">標籤</h3>
                            <div class="ts basic fitted segment">
                                {!! $productTagsHtml !!}
                            </div>
                        </div>
                    @endif

                    @if ($categories->isNotEmpty())
                        {{-- 麵包屑只走一條路徑，這裡列出商品其他掛得上的分類，讓人往回逛 --}}
                        <div class="sixteen wide column">
                            <h3 class="ts small header">所屬分類</h3>
                            <div class="ts basic fitted segment">
                                @foreach ($categories as $category)
                                    <a class="ts small basic label"
                                        href="{{ route('categories.show', [
                                            'brand' => $category->brand->slug(),
                                            'code' => $category->code,
                                        ]) }}">
                                        {{ $category->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (!empty($productFacts))
                        <div class="sixteen wide column">
                            <h3 class="ts small header">商品資訊</h3>
                            <table class="ts small basic table">
                                <tbody>
                                    @foreach ($productFacts as $label => $value)
                                        <tr>
                                            <td>{{ $label }}</td>
                                            <td>{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="sixteen wide column">
                        <div class="ts basic horizontally fitted segment" id="comment">
                            <p>{!! $descriptionHtml !!}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.section-menu', ['id' => 'product_menu', 'items' => $sections, 'fluid' => true])

    @if (optional($japanProduct)->has_videos)
        <div class="ts very padded horizontally fitted attached fluid tertiary segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'videos', 'menu' => 'product_menu'])
                <h2 class="ts large dividing header">
                    商品影片
                    <div class="inline sub header">日本版</div>
                </h2>
                <div class="ts hidden divider"></div>
                <div class="ts doubling four flatted cards">
                    @foreach ($japanProduct->sub_videos as $key => $subVideo)
                        <div class="ts card">
                            <div class="video">
                                <video preload="metadata" src="{{ $subVideo }}" autoplay muted loop controls
                                    playsinline></video>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($colorNums || optional($japanProduct)->main_images || optional($japanProduct)->sub_images)
        <div class="ts very padded horizontally fitted attached fluid tertiary segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'photos', 'menu' => 'product_menu'])
                <h2 class="ts large dividing header">商品實照</h2>
                <div class="ts hidden divider"></div>
                <div class="ts doubling four flatted cards">
                    @if ($colorNums)
                        @foreach ($colorNums as $key => $colorNum)
                            <x-image-card imageUrl="{{ $hmallProductPresenter->getSkuPic($hmallProduct, $colorNum) }}"
                                largeImageUrl="{{ $hmallProductPresenter->getSkuPic($hmallProduct, $colorNum) }}"
                                link="" alt="商品實照 {{ $key + 1 }}" />
                        @endforeach
                    @endif
                    @if (optional($japanProduct)->main_images)
                        @foreach ($japanProduct->main_images as $key => $mainImage)
                            <x-image-card imageUrl="{{ $mainImage }}" largeImageUrl="{{ $mainImage }}"
                                country="jp" link="" alt="日本版商品穿搭照 {{ $key + 1 }}" width="561"
                                height="561" />
                        @endforeach
                    @endif
                    @if (optional($japanProduct)->sub_images)
                        @foreach ($japanProduct->sub_images as $key => $subImage)
                            <x-image-card imageUrl="{{ $subImage }}" largeImageUrl="{{ $subImage }}"
                                country="jp" link="" alt="日本版商品實照 {{ $key + 1 }}" width="561"
                                height="561" />
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($styles->isNotEmpty())
        <div class="ts very padded horizontally fitted attached fluid tertiary segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'styles', 'menu' => 'product_menu'])
                <h2 class="ts large dividing header">Official Styling 官方精選穿搭</h2>
                <div class="ts hidden divider"></div>
                <div class="ts doubling four flatted cards">
                    @foreach ($styles as $key => $style)
                        <x-image-card link="{{ $style->detail_url }}" imageUrl="{{ $style->image_url }}"
                            largeImageUrl="{{ $style->large_image_url }}"
                            alt="Official Styling 官方精選穿搭 {{ $key + 1 }}" width="720" height="960" />
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($styleHints->isNotEmpty())
        <div class="ts very padded horizontally fitted attached fluid tertiary segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'style-hints', 'menu' => 'product_menu'])
                <h2 class="ts large dividing header">
                    StyleHint 網友穿搭靈感
                    <div class="inline sub header">共 {{ $styleHintCount }} 張</div>
                    <a class="ts right floated icon labeled button" style="font-size: 0.9rem;"
                        href="{{ $hmallProductPresenter->getStyleHintsRoute($hmallProduct) }}">
                        <i class="camera retro icon"></i>
                        查看列表
                    </a>
                </h2>
                <div class="ts hidden divider"></div>
                <div class="ts doubling four flatted cards">
                    @foreach ($styleHints as $key => $styleHint)
                        <x-image-card link="{{ $styleHint->official_site_url }}" imageUrl="{{ $styleHint->image_url }}"
                            largeImageUrl="{{ $styleHint->large_image_url }}" country="{{ $styleHint->country }}"
                            alt="StyleHint 網友穿搭靈感 {{ $key + 1 }} ({{ $styleHint->user_name }})" width="720"
                            height="960" />
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($commonlyStyledHmallProducts->isNotEmpty())
        <div class="ts very padded horizontally fitted attached fluid tertiary segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'commonly-styled', 'menu' => 'product_menu'])
                <h2 class="ts large dividing header">經常搭配商品</h2>
                <div class="ts hidden divider"></div>
                <div class="ts doubling link cards six">
                    @each('hmall-products.simple-card', $commonlyStyledHmallProducts, 'hmallProduct')
                </div>
            </div>
        </div>
    @endif

    @if ($relatedHmallProducts->isNotEmpty())
        <div class="ts very padded horizontally fitted attached fluid tertiary segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'related', 'menu' => 'product_menu'])
                <h2 class="ts large dividing header">延伸商品</h2>
                <div class="ts hidden divider"></div>
                <div class="ts doubling link cards six">
                    @each('hmall-products.card', $relatedHmallProducts, 'hmallProduct')
                </div>
            </div>
        </div>
    @endif

    @if (!empty($adsenseClientId) && !empty($adsenseSlotId))
        <div class="ts very padded horizontally fitted attached fluid secondary segment">
            <div class="ts container">
                <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $adsenseClientId }}"
                    crossorigin="anonymous"></script>
                <ins class="adsbygoogle" style="display:block; text-align:center;" data-ad-layout="in-article"
                    data-ad-format="fluid" data-ad-client="{{ $adsenseClientId }}" data-ad-slot="{{ $adsenseSlotId }}">
                </ins>
                <script>
                    (adsbygoogle = window.adsbygoogle || []).push({});
                </script>
            </div>
        </div>
    @endif

    <div class="ts very padded horizontally fitted attached fluid tertiary segment">
        <div class="ts container">
            @include('partials.section-anchor', ['anchor' => 'price-history', 'menu' => 'product_menu'])
                <h2 class="ts large dividing header">歷史價格</h2>
            <div class="ts hidden divider"></div>
            <div class="ts fluid container grid">
                <div class="four wide computer sixteen wide tablet sixteen wide mobile column">
                    <div class="ts grid">
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
                    <div class="ts items">
                        <div class="item">
                            <div class="ts mini image">
                                <x-lazy-load-image src="{{ $hmallProductPresenter->getMainFirstPic($hmallProduct) }}"
                                    alt="{{ $hmallProductPresenter->getFullNameWithCodeAndProductCode($hmallProduct) }}"
                                    width="1" height="1" />
                            </div>
                            <div class="middle aligned content">
                                <div class="header">
                                    {{ $hmallProductPresenter->getFullName($hmallProduct) }}
                                </div>
                                <div class="inline middoted meta">
                                    <span>{{ $hmallProduct->brand }} 商品編號 {{ $hmallProduct->code }}
                                        {{ $hmallProduct->product_code }}</span>
                                    {!! $hmallProductPresenter->getRatingForProductCardAndItem($hmallProduct) !!}
                                    <span>NT${{ $hmallProduct->price }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ts flatted card">
                        <div class="image">
                            <canvas id="priceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @isset($japanProduct)
        <div class="ts very padded horizontally fitted attached fluid tertiary segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'japan', 'menu' => 'product_menu'])
                <h2 class="ts large dividing header">日本版商品資訊</h2>
                <div class="ts hidden divider"></div>
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
                <div class="ts doubling four column grid">
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
                        @if ($japanProduct->brand === 'GU' && collect($japanProduct->prices)->count() >= 2)
                            <a class="ts right floated tiny basic info right labeled icon button"
                                href="https://www.gu-global.com/jp/ja/search?q={{ $japanProduct->l1Id }}" target="_blank"
                                rel="nofollow noopener" aria-label="GU">前往日本 GU 官網<i class="external icon"></i></a>
                        @elseif ($japanProduct->brand === 'GU' && collect($japanProduct->prices)->count() <= 1)
                            <a class="ts right floated tiny basic info right labeled icon button"
                                href="https://www.gu-global.com/jp/ja/products/{{ $japanProduct->product_id }}"
                                target="_blank" rel="nofollow noopener" aria-label="GU">前往日本 GU 官網<i
                                    class="external icon"></i></a>
                        @elseif ($japanProduct->brand === 'UNIQLO' && collect($japanProduct->prices)->count() >= 2)
                            <a class="ts right floated tiny basic negative right labeled icon button"
                                href="https://www.uniqlo.com/jp/ja/search?q={{ $japanProduct->l1Id }}" target="_blank"
                                rel="nofollow noopener" aria-label="UNIQLO">前往日本 UNIQLO 官網<i class="external icon"></i></a>
                        @elseif ($japanProduct->brand === 'UNIQLO' && collect($japanProduct->prices)->count() <= 1)
                            <a class="ts right floated tiny basic negative right labeled icon button"
                                href="https://www.uniqlo.com/jp/ja/products/{{ $japanProduct->product_id }}" target="_blank"
                                rel="nofollow noopener" aria-label="UNIQLO">前往日本 UNIQLO 官網<i class="external icon"></i></a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endisset

    @if ($relatedProducts->isNotEmpty())
        <div class="ts very padded horizontally fitted attached fluid tertiary segment">
            <div class="ts container">
                @include('partials.section-anchor', ['anchor' => 'legacy', 'menu' => 'product_menu'])
                <h2 class="ts large dividing header">舊系統商品</h2>
                <div class="ts hidden divider"></div>
                <div class="ts doubling link cards six">
                    @each('products.card', $relatedProducts, 'product')
                </div>
            </div>
        </div>
    @endif
@endsection

@section('javascript')
    {{-- 版號帶檔案的 mtime：這支 JS 跟頁面上的 id 與 data 屬性是綁在一起的， --}}
    {{-- 瀏覽器留著舊快取配新 HTML 會找不到元素而中斷。 --}}
    <script src="{{ asset('js/favorites.js') }}?v={{ filemtime(public_path('js/favorites.js')) }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.bundle.min.js"
        integrity="sha512-SuxO9djzjML6b9w9/I07IWnLnQhgyYVSpHZx0JV97kGBfTIsUYlWflyuW4ypnvhBrslz1yJ3R+S14fdCWmSmSA=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox-plus-jquery.min.js"
        integrity="sha512-U9dKDqsXAE11UA9kZ0XKFyZ2gQCj+3AwZdBMni7yXSvWqLFEj8C1s7wRmWl9iyij8d5zb4wm56j4z/JVEwS77g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

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

        lightbox.option({
            'alwaysShowNavOnTouchDevices': true,
            'albumLabel': '相片 %1 / %2',
            'disableScrolling': true,
            'fadeDuration': 150,
            'resizeDuration': 150,
            'imageFadeDuration': 0,
        });

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
                document.getElementById('share').style.display = 'block';
            }

            document.querySelector('#share').addEventListener('click', webShare);
        }

        window.addEventListener('load', onLoad);
    </script>
@endsection
