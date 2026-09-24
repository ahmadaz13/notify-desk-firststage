@php
    $locale = str_replace('_', '-', app()->getLocale());
    $language = strtolower(str($locale)->before('-')->toString());
    $direction = in_array($language, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    // Pages opt into the wide container with @section('shell_width', 'wide') (dense dashboards/tables only).
    $shellWidth = trim($__env->yieldContent('shell_width', 'normal'));
@endphp

<x-notify.app-shell :title="$title ?? 'Notify Desk'" :locale="$locale" :direction="$direction" :width="$shellWidth">
    @yield('content')
</x-notify.app-shell>
