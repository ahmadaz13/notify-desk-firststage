@props([
    'title' => null,
    'message' => null,
    'actionHref' => null,
    'actionLabel' => null,
    'icon' => 'activity',
    'compact' => false,
])

@php
    $resolvedTitle = $title ?? __('notify.common.clear');
    $resolvedMessage = $message ?? __('notify.common.no_records');
@endphp

<div {{ $attributes->merge(['class' => 'notify-empty-state'.($compact ? ' notify-empty-state--compact' : '')]) }}>
    @unless($compact)
        <span class="notify-empty-state__icon">
            <x-notify.icon :name="$icon" :size="24" />
        </span>
    @endunless
    <h3>{{ $resolvedTitle }}</h3>
    <p>{{ $resolvedMessage }}</p>
    @if($actionHref && $actionLabel)
        <x-notify.button :href="$actionHref" variant="primary" icon="plus">{{ $actionLabel }}</x-notify.button>
    @endif
</div>