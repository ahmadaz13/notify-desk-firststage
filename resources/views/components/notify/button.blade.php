@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'hideLabelOnMobile' => false,
])

@php
    $classes = 'notify-button notify-button--'.$variant.($hideLabelOnMobile ? ' notify-button--hide-label-sm' : '');
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <x-notify.icon :name="$icon" />
        @endif
        <span class="notify-button__label">{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <x-notify.icon :name="$icon" />
        @endif
        <span class="notify-button__label">{{ $slot }}</span>
    </button>
@endif
