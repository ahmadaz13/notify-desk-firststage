{{-- Installable PWA metadata + service-worker registration (P9.1). Used by the app shell and the login page only. --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ \App\Support\Pwa::THEME_COLOR }}">
<meta name="application-name" content="{{ \App\Support\Pwa::NAME }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \App\Support\Pwa::SHORT_NAME }}">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="{{ asset('pwa/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('pwa/favicon-32.png') }}">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(@json(route('pwa.service-worker', [], false)), { scope: @json(\App\Support\Pwa::scope()) }).catch(function () {});
        });
    }
</script>
