@props([
    'title',
    'subtitle' => null,
    'badge' => null,
    'badgeVariant' => 'neutral',
    'open' => false,
    'icon' => null,
    'id' => null,
])

<details {{ $attributes->merge(['class' => 'notify-collapsible']) }} @if($open) open @endif @if($id) id="{{ $id }}" @endif>
    <summary class="notify-collapsible__summary">
        <div class="notify-collapsible__header">
            @if($icon)
                <span class="notify-collapsible__icon">
                    <x-notify.icon :name="$icon" :size="18" />
                </span>
            @endif
            <div class="notify-collapsible__titles">
                <h3 class="notify-collapsible__title">{{ $title }}</h3>
                @if($subtitle)
                    <p class="notify-collapsible__subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            @if($badge !== null)
                <span class="notify-badge notify-badge--{{ $badgeVariant }}">{{ $badge }}</span>
            @endif
        </div>
        <span class="notify-collapsible__chevron" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </span>
    </summary>
    <div class="notify-collapsible__content">
        {{ $slot }}
    </div>
</details>
