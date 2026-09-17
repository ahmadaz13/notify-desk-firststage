@props([
    'variant' => 'neutral',
    'icon' => null,
])

<span {{ $attributes->merge(['class' => 'notify-badge notify-badge--'.$variant]) }}>
    @if($icon)
        <x-notify.icon :name="$icon" :size="14" />
    @endif
    {{ $slot }}
</span>
