@php
    // Stored once by the service worker and shown when a page cannot be reached, so it carries
    // no user or business data and shows both languages (current locale first).
    $primary = app()->getLocale() === 'en' ? 'en' : 'ar';
    $secondary = $primary === 'ar' ? 'en' : 'ar';
    $dir = fn (string $locale) => $locale === 'ar' ? 'rtl' : 'ltr';
@endphp
<!doctype html>
<html lang="{{ $primary }}" dir="{{ $dir($primary) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="{{ \App\Support\Pwa::THEME_COLOR }}">
    <meta name="robots" content="noindex">
    <title>{{ __('notify.pwa.offline.page_title', [], $primary) }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="notify-offline-page">
<main class="notify-offline" data-offline-page>
    <img class="notify-offline__mark" src="{{ asset('pwa/icon-192.png') }}" alt="{{ \App\Support\Pwa::NAME }}" width="72" height="72">

    <section class="notify-offline__message" lang="{{ $primary }}" dir="{{ $dir($primary) }}">
        <h1 class="notify-offline__title">{{ __('notify.pwa.offline.title', [], $primary) }}</h1>
        <p>{{ __('notify.pwa.offline.message', [], $primary) }}</p>
    </section>

    <section class="notify-offline__message notify-offline__message--secondary" lang="{{ $secondary }}" dir="{{ $dir($secondary) }}">
        <h2 class="notify-offline__subtitle">{{ __('notify.pwa.offline.title', [], $secondary) }}</h2>
        <p>{{ __('notify.pwa.offline.message', [], $secondary) }}</p>
    </section>

    <button type="button" class="notify-button notify-button--primary notify-offline__retry" data-offline-retry>
        <span lang="{{ $primary }}">{{ __('notify.pwa.offline.retry', [], $primary) }}</span>
        <span aria-hidden="true">·</span>
        <span lang="{{ $secondary }}">{{ __('notify.pwa.offline.retry', [], $secondary) }}</span>
    </button>
    <p class="notify-offline__hint">
        <span lang="{{ $primary }}">{{ __('notify.pwa.offline.hint', [], $primary) }}</span>
        <span lang="{{ $secondary }}" dir="{{ $dir($secondary) }}">{{ __('notify.pwa.offline.hint', [], $secondary) }}</span>
    </p>
</main>
<script>
    // Retry reloads the page the user was opening; it also reloads automatically once back online.
    document.querySelector('[data-offline-retry]').addEventListener('click', function () { window.location.reload(); });
    window.addEventListener('online', function () { window.location.reload(); });
</script>
</body>
</html>
