@props([
    'title' => null,
    'message' => null,
    'actionHref' => null,
    'actionLabel' => null,
    'actionIcon' => 'plus',
    'actionVariant' => 'primary',
    'icon' => 'activity',
    'compact' => false,
])

{{-- Empty state (P12): concise title, optional one-line explanation, at most one useful action. --}}
@php
    $resolvedTitle = $title ?? __('notify.common.clear');
    $resolvedMessage = $message;
    if ($resolvedMessage === null && ! $compact) {
        $resolvedMessage = __('notify.common.no_records');
    }
@endphp

<div {{ $attributes->merge(['class' => 'notify-empty-state'.($compact ? ' notify-empty-state--compact' : '')]) }} data-empty-state>
    @unless($compact)
        <span class="notify-empty-state__icon">
            <x-notify.icon :name="$icon" :size="24" />
        </span>
    @endunless
    <h3>{{ $resolvedTitle }}</h3>
    @if(filled($resolvedMessage))
        <p>{{ $resolvedMessage }}</p>
    @endif
    @if($actionHref && $actionLabel)
        <x-notify.button :href="$actionHref" :variant="$actionVariant" :icon="$actionIcon">{{ $actionLabel }}</x-notify.button>
    @endif
</div>
