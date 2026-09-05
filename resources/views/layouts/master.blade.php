<!DOCTYPE html>
<html lang="zh-Hant-TW">

<head>
    @include('layouts.google-analytics')

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#ce5e57">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="manifest" href="{{ asset('app.webmanifest') }}">
    <meta name="apple-mobile-web-app-title" content="UQ 搜尋">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">

    <title>@yield('title') | UQ 搜尋</title>

    @yield('json-ld')

    @yield('metadata')

    <link rel="preconnect" href="https://im.uniqlo.com">
    <link rel="preconnect" href="https://www.uniqlo.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://im.uniqlo.com">
    <link rel="dns-prefetch" href="https://www.uniqlo.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

    <!-- Tocas UI：CSS 與元件 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tocas-ui/2.3.3/tocas.css"
        integrity="sha512-D41DQHff3/kvdRtWlfJ69BltxL2ovJ2hRFiQopYGGiSFgJE4i5Un3qaqlKCAuo+00yaMzdcw7aVRl11taevIdw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    {{-- 跨頁共用的元件樣式。放在 Tocas 之後才蓋得掉它，放在 @yield('css') 之前才蓋得掉這裡 --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        html {
            height: 100%;
            scroll-behavior: smooth;
            scroll-padding-top: 75px;
        }

        body {
            height: 100%;
            padding: 60px 0 0 0;
            display: flex;
            flex-direction: column;
        }

        .wrapper {
            flex-grow: 1;
        }

        .back-to-top {
            position: fixed;
            right: 2rem;
            bottom: 2rem;
            z-index: 20;
            opacity: 100%;
            transition: opacity 0.2s;
        }

        .back-to-top.hidden {
            opacity: 0%;
        }
    </style>
    @yield('css')

    @include('layouts.facebook-pixel')
</head>

<body>
    @include('layouts.nav')
    <div class="wrapper">
        @yield('content')
    </div>
    @include('layouts.footer')

    <span class="back-to-top hidden">
        <button class="ts circular secondary opinion icon button">
            <i class="arrow up icon"></i>
        </button>
    </span>

    <!-- Tocas JS：模塊與 JavaScript 函式 -->
    <script src="{{ asset('js/tocas.js') }}"></script>
    {{-- 收藏鈕現在出現在每一頁的商品卡片上，所以這支腳本改由版型統一載入。 --}}
    {{-- 版號帶檔案的 mtime：它跟頁面上的 id 與 data 屬性是綁在一起的， --}}
    {{-- 瀏覽器留著舊快取配新 HTML 會找不到元素而中斷。 --}}
    <script src="{{ asset('js/favorites.js') }}?v={{ filemtime(public_path('js/favorites.js')) }}"></script>
    <script>
        if ('loading' in HTMLImageElement.prototype) {
            const images = document.querySelectorAll('img[loading="lazy"]');
            images.forEach(img => {
                img.src = img.dataset.src;
            });
        } else {
            // Dynamically import the LazySizes library
            const script = document.createElement('script');

            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js';
            script.integrity =
                'sha512-q583ppKrCRc7N5O0n2nzUiJ+suUv7Et1JGels4bXOaMFQcamPk9HjdUknZuuFjBNs7tsMuadge5k9RzdmO+1GQ==';
            script.crossOrigin = 'anonymous';
            script.referrerPolicy = 'no-referrer';
            script.async = true;

            document.body.appendChild(script);
        }
    </script>
    <script>
        ts('.ts.dropdown:not(.basic)').dropdown();

        // tocas.js 的 dropdown 沒有 toggle：它每次點擊都先收合所有展開的、再展開
        // 自己（tocas.js 的 ts.fn.dropdown），所以點第二次還是開著。這裡在捕獲階段
        // 攔下「已經開著時的點擊」，處理完就擋住它的 handler。
        document.querySelectorAll('.ts.dropdown:not(.basic)').forEach(function(dropdown) {
            dropdown.addEventListener('click', function(event) {
                if (!dropdown.classList.contains('visible')) {
                    return;
                }

                event.stopImmediatePropagation();
                dropdown.classList.remove('visible');
                dropdown.classList.add('hidden');
            }, true);
        });
    </script>
    <script>
        /*
         * 排序這種「選了就該生效」的下拉。沒有 JavaScript 時旁邊那顆送出鈕是
         * 唯一的出路，所以是它被藏起來、不是反過來讓下拉在沒有腳本時失效。
         */
        document.querySelectorAll('select[data-auto-submit]').forEach(function(select) {
            select.addEventListener('change', function() {
                select.form.submit();
            });

            select.form.querySelectorAll('[data-auto-submit-fallback]').forEach(function(button) {
                button.hidden = true;
            });
        });
    </script>
    <script>
        // 章節選單：每頁最多一個，有就把捲動監聽掛上去，active 才會跟著捲動走。
        // id 在選單本身，不在外面那層負責 sticky 的容器。
        const sectionMenu = document.querySelector('.uq-section-menu .ts.menu');

        if (sectionMenu) {
            ts('body').scrollspy({
                target: '#' + sectionMenu.id
            });

            // scrollspy 判斷 active 的條件是「這一段的錨點捲到離容器頂 10px 以
            // 內」，初始化當下就算對每個錨點都跑過一次同樣的判斷，頁面剛載入、
            // 使用者還沒捲動時第一段通常在畫面中段而不是頂端，條件不成立，選單
            // 就完全沒有 active、看起來像純文字。這裡補上：初始化後如果還沒有
            // 任何一項是 active，先讓第一項頂著，之後使用者一捲動就交還給
            // scrollspy 接手（它會依實際位置正確地加或拿掉 active）。
            if (!sectionMenu.querySelector('.item.active')) {
                const firstItem = sectionMenu.querySelector('.item');

                if (firstItem) {
                    firstItem.classList.add('active');
                }
            }
        }
    </script>
    <script>
        const showOnPx = 100;
        const backToTopButton = document.querySelector(".back-to-top")

        const scrollContainer = () => {
            return document.documentElement || document.body;
        };

        document.addEventListener("scroll", () => {
            if (scrollContainer().scrollTop > showOnPx) {
                backToTopButton.classList.remove("hidden")
            } else {
                backToTopButton.classList.add("hidden")
            }
        })

        const goToTop = () => {
            document.body.scrollIntoView();
        };

        backToTopButton.addEventListener("click", goToTop)
    </script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ asset('service-worker.js') }}');
            });
        }
    </script>

    @yield('javascript')
</body>

</html>
