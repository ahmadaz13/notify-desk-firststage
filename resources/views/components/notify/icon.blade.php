@props([
    'name' => 'circle',
    'size' => 20,
    'strokeWidth' => 2,
])

@php
    $icons = [
        'activity' => 'activity',
        'alert-circle' => 'alert-circle',
        'bell' => 'bell',
        'book-open' => 'book-open',
        'briefcase' => 'briefcase',
        'building' => 'building',
        'chart' => 'chart-column',
        'calendar' => 'calendar-days',
        'check' => 'check',
        'chevron-down' => 'chevron-down',
        'chevron-left' => 'chevron-left',
        'chevron-right' => 'chevron-right',
        'check-circle' => 'circle-check',
        'circle' => 'circle',
        'clipboard-list' => 'clipboard-list',
        'file-chart' => 'file-chart-column',
        'ellipsis' => 'ellipsis',
        'folder-kanban' => 'folder-kanban',
        'home' => 'home',
        'key-round' => 'key-round',
        'landmark' => 'landmark',
        'languages' => 'languages',
        'layout-dashboard' => 'layout-dashboard',
        'log-out' => 'log-out',
        'menu' => 'menu',
        'message-circle' => 'message-circle',
        'package' => 'package',
        'panel-left' => 'panel-left',
        'phone' => 'phone',
        'plus' => 'plus',
        'receipt' => 'receipt',
        'search' => 'search',
        'settings' => 'settings',
        'trending-up' => 'trending-up',
        'upload' => 'upload',
        'user' => 'user',
        'user-cog' => 'user-cog',
        'users' => 'users',
        'wallet' => 'wallet',
        'wrench' => 'wrench',
        'x' => 'x',
    ];

    $lucideName = $icons[$name] ?? $icons['circle'];
@endphp

<span {{ $attributes->merge(['class' => 'notify-icon']) }} aria-hidden="true">
    <i data-lucide="{{ $lucideName }}" width="{{ $size }}" height="{{ $size }}" stroke-width="{{ $strokeWidth }}"></i>
</span>
