<!DOCTYPE html>
<html>
    <head>
        @include('layouts.google-analytics')

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="theme-color" content="#ce5e57">

        <title>@yield('title') - UQ 搜尋</title>

        @yield('metadata')

        <!-- Tocas UI：CSS 與元件 -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tocas-ui/2.3.3/tocas.css">
        <style type="text/css">
            body {
                padding: 80px 0;
            }
        </style>
    </head>

    <body>
        @include('layouts.nav')
        @yield('content')
        @include('layouts.footer')

        <!-- Tocas JS：模塊與 JavaScript 函式 -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/tocas-ui/2.3.3/tocas.js"></script>

        @yield('javascript')
    </body>
</html>
