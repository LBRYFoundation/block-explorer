<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        LBRY Block Explorer &bull; @yield('title')
    </title>
    {{--TODO: icon--}}

    <script src="/js/{{ 'jquery' }}.js"></script>
    <script src="/js/{{ 'moment' }}.js"></script>

    <script src="/js/{{ 'moment' }}.js"></script>

    <link rel="stylesheet" href="/css/{{ 'main' }}.css" />

    <script src="https://use.typekit.net/yst3vhs.js"></script>
    <script>try{Typekit.load({ async: true });}catch(e){}</script>

    <!-- Analytics -->
    @if(env('GTM_ID'))
        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="//www.googletagmanager.com/gtag/js?id={{ env('GTM_ID') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ env('GTM_ID') }}');
        </script>
    @endif

    <script type="text/javascript">
        // Handle Coinomi and LBRY app URLs
        var hashpart = window.location.hash;
        if (hashpart.length > 3) {
            hashpart = hashpart.substring(3);
            var txhash = null;
            if (hashpart.indexOf('?id=') > -1) {
                txhash = hashpart.substring(hashpart.indexOf('?id=') + 4);
            } else {
                var parts = hashpart.split('/');
                if (parts.length > 1) {
                    txhash = parts[1];
                }
            }

            if (txhash && $.trim(txhash.trim).length > 0) {
                window.location.href = '/tx/' + txhash;
            }
        }
    </script>

    @yield('meta')
    @yield('css')
    @yield('script')
</head>
<body>
@yield('content')
<footer>
    <div class="content">
        <a href="https://lbry.org">LBRY</a>

        <div class="page-time">Page took {{ round((microtime(true) - LARAVEL_START) * 1000, 0) }}ms</div>
    </div>
</footer>
</body>
</html>
