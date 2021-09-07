<!DOCTYPE html>
<html lang="zh-Hant-TW">

<head>
    @include('layouts.google-analytics')

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#ce5e57">
    <link rel="shortcut icon" href="/favicon.ico">

    <title>@yield('title') | UNIQLO 比價 | UQ 搜尋</title>

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
        integrity="sha256-QKCF3aMJvlWKdpmS+c89QwyQdOjreJwF8Sohw+G4C+0=" crossorigin="anonymous" />
    <style>
        body {
            padding: 60px 0 0 0;
        }
    </style>
    @yield('css')

    @include('layouts.facebook-pixel')
</head>

<body>
    @include('layouts.nav')
    <div class="ts horizontally fitted attached fluid segment">
        <div class="ts container">
            <div class="ts mini center aligned borderless message">
                本站尚未支援新版 UNIQLO 官網，目前網站內容皆為過往資訊，部分功能亦受到限制，敬請見諒。
            </div>
        </div>
    </div>
    @yield('content')
    @include('layouts.footer')

    <!-- Tocas JS：模塊與 JavaScript 函式 -->
    <script src="{{ asset('js/tocas.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.2.0/lazysizes.min.js"
        integrity="sha256-h2tMEmhemR2IN4wbbdNjj9LaDIjzwk2hralQwfJmBOE=" crossorigin="anonymous" async></script>
    <script>
        ts('.ts.dropdown:not(.basic)').dropdown();
    </script>

    @yield('javascript')
</body>

</html>
