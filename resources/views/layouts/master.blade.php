<!DOCTYPE html>
<html>
    <head>
        @include('layouts.google-analytics')

        <meta charset="utf-8">

        <title>@yield('title') - UQ 搜尋</title>

        @yield('metadata')

        <!-- Tocas UI：CSS 與元件 -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tocas-ui/2.3.3/tocas.css">

        <style>
            body {
                padding: 90px 0;
            }
        </style>
    </head>

    <body>
        @include('layouts.nav')
        <!-- 主要內容網格容器 -->
        <div class="ts narrow container relaxed grid">
            <div class="column">
                @yield('content')
                @include('layouts.footer')
            </div>
        </div>
        <!-- / 主要內容網格容器 -->

        <!-- Tocas JS：模塊與 JavaScript 函式 -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/tocas-ui/2.3.3/tocas.js"></script>
    </body>
</html>
