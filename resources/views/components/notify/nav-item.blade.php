@props([
    'href',
    'label',
    'icon' => 'circle',
    'active' => false,
])

<a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => 'notify-nav-item'.($active ? ' is-active' : '')]) }} @if($active) aria-current="page" @endif>
    <span class="notify-nav-item__icon">
        <x-notify.icon :name="$icon" />
    </span>
    <span class="notify-nav-item__text">{{ $label }}</span>
</a>
