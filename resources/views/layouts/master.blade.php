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
