@php
    $locale = str_replace('_', '-', app()->getLocale());
    $language = strtolower(str($locale)->before('-')->toString());
    $direction = in_array($language, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
@endphp

<x-notify.app-shell :title="$title ?? 'Notify Desk'" :locale="$locale" :direction="$direction">
    @yield('content')
</x-notify.app-shell>
