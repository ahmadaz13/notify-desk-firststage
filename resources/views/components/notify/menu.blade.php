@props([
    'label' => null,
    'icon' => 'ellipsis',
    'showLabel' => false,
    'triggerClass' => 'notify-button notify-button--ghost',
])

{{--
    "…" overflow menu (P12, generalised from P10). Infrequent actions live here; destructive ones go in
    the `danger` slot, separated from routine items. Items: `.notify-menu__item` buttons/links with
    role="menuitem" (a sheet opener, a link, or a submit inside a `form[data-confirm]`).
    Keyboard, outside-click, one-open-at-a-time and viewport-safe placement: resources/js/interactions.js.
--}}
@php($label ??= __('notify.ui.more_actions'))
<details {{ $attributes->merge(['class' => 'notify-menu']) }} data-menu>
    <summary class="{{ $triggerClass }} notify-menu__trigger" aria-haspopup="menu" aria-label="{{ $label }}" title="{{ $label }}">
        <x-notify.icon :name="$icon" :size="20" />
        @if($showLabel)
            <span class="notify-button__label">{{ $label }}</span>
        @endif
    </summary>
    <div class="notify-menu__panel" role="menu" aria-label="{{ $label }}">
        {{ $slot }}
        @isset($danger)
            @if(trim((string) $danger) !== '')
                <div class="notify-menu__separator" role="separator"></div>
                {{ $danger }}
            @endif
        @endisset
    </div>
</details>
