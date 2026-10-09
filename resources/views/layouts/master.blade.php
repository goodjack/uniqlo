<!DOCTYPE html>
<html lang="zh-Hant-TW">

<head>
    @include('layouts.google-analytics')

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#ce5f58">
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
            padding: var(--uq-nav-height) 0 0 0;
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
    {{-- 每一頁的商品卡片都有收藏鈕；版號帶 mtime，避免舊快取的腳本配上新的 HTML --}}
    <script src="{{ asset('js/favorites.js') }}?v={{ filemtime(public_path('js/favorites.js')) }}"></script>
    <script>
        (function () {
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
        })();
    </script>
    <script>
        (function () {
            ts('.ts.dropdown:not(.basic)').dropdown();

            function close(dropdown) {
                dropdown.classList.remove('visible');
                dropdown.classList.add('hidden');
            }

            document.querySelectorAll('.ts.dropdown:not(.basic)').forEach(function(dropdown) {
                // tocas.js 的 dropdown 點第二次不會收合（它每次都先全部收合再展開自己），
                // 在捕獲階段攔下「已經開著時的點擊」自己收合
                dropdown.addEventListener('click', function(event) {
                    if (!dropdown.classList.contains('visible')) {
                        return;
                    }

                    event.stopImmediatePropagation();
                    close(dropdown);
                }, true);

                // 觸發字是 <button>，Enter／Space 原生就會送 click；tocas.js 只認滑鼠，
                // 展開狀態、Esc 與 Tab 離開都要自己補
                const trigger = dropdown.querySelector(':scope > button.text');

                if (!trigger) {
                    return;
                }

                new MutationObserver(function() {
                    trigger.setAttribute('aria-expanded', dropdown.classList.contains('visible') ? 'true' : 'false');
                }).observe(dropdown, { attributes: true, attributeFilter: ['class'] });

                dropdown.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && dropdown.classList.contains('visible')) {
                        close(dropdown);
                        trigger.focus();
                    }
                });

                // 只在焦點確定移到選單外的元素時收合。relatedTarget 是 null 的情況
                // （Safari 點連結不給焦點）交給 tocas.js 的點擊外部收合，不然選單會在
                // mousedown 時先消失，連結點不到
                dropdown.addEventListener('focusout', function(event) {
                    if (event.relatedTarget && !dropdown.contains(event.relatedTarget)) {
                        close(dropdown);
                    }
                });
            });
        })();
    </script>
    <script>
        (function () {
            // 選了就送出；送出鈕留給沒有 JavaScript 的情況，有 JavaScript 才藏起來
            document.querySelectorAll('select[data-auto-submit]').forEach(function(select) {
                select.addEventListener('change', function() {
                    select.form.submit();
                });

                select.form.querySelectorAll('[data-auto-submit-fallback]').forEach(function(button) {
                    button.hidden = true;
                });
            });
        })();
    </script>
    <script>
        (function () {
            const sectionMenu = document.querySelector('.uq-section-menu .ts.menu');

            if (sectionMenu) {
                ts('body').scrollspy({
                    target: '#' + sectionMenu.id
                });

                // scrollspy 要等某段捲到頂端才標 active，剛載入時選單會一項都沒標、
                // 看起來像純文字，先標第一個連結（分類總覽第一項是品牌小標，不算）
                if (!sectionMenu.querySelector('.item.active')) {
                    const firstItem = sectionMenu.querySelector('a.item');

                    if (firstItem) {
                        firstItem.classList.add('active');
                    }
                }
            }
        })();
    </script>
    <script>
        (function () {
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
        })();
    </script>
    <script>
        (function () {
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register(@js(asset('service-worker.js')));
                });
            }
        })();
    </script>

    @yield('javascript')
</body>

</html>
