@props([
    'action',
    'variant' => 'secondary',
    'menu' => false,
])

{{-- One client action (P10): opens its focused sheet, or follows a link. Built by ClientWorkspaceViewModel. --}}
@php
    $classes = $menu
        ? 'notify-menu__item'.(($action['tone'] ?? null) === 'danger' ? ' notify-menu__item--danger' : '')
        : 'notify-button notify-button--'.$variant;
@endphp
@if(isset($action['sheet']))
    <button type="button" {{ $attributes->merge(['class' => $classes]) }} data-open-sheet="{{ $action['sheet'] }}" data-workspace-action="{{ $action['key'] }}" aria-haspopup="dialog">
        <x-notify.icon :name="$action['icon']" :size="18" />
        <span class="notify-button__label">{{ $action['label'] }}</span>
    </button>
@else
    <a href="{{ $action['href'] }}" {{ $attributes->merge(['class' => $classes]) }} data-workspace-action="{{ $action['key'] }}">
        <x-notify.icon :name="$action['icon']" :size="18" />
        <span class="notify-button__label">{{ $action['label'] }}</span>
    </a>
@endif
