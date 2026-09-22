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
        'briefcase' => 'briefcase',
        'building' => 'building',
        'chart' => 'chart-column',
        'calendar' => 'calendar-days',
        'check' => 'check',
        'chevron-down' => 'chevron-down',
        'chevron-left' => 'chevron-left',
        'chevron-right' => 'chevron-right',
        'circle' => 'circle',
        'clipboard-list' => 'clipboard-list',
        'home' => 'home',
        'landmark' => 'landmark',
        'languages' => 'languages',
        'log-out' => 'log-out',
        'menu' => 'menu',
        'package' => 'package',
        'panel-left' => 'panel-left',
        'phone' => 'phone',
        'plus' => 'plus',
        'settings' => 'settings',
        'user' => 'user',
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
