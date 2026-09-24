@props([
    'id' => 'search',
    'name' => 'q',
    'value' => '',
    'label' => null,
    'placeholder' => null,
    'clearHref' => null,
])

{{-- Search field (P12): icon, real label, Enter submits the surrounding GET form, clear link when filled. --}}
@php($label ??= __('notify.ui.search'))
<div {{ $attributes->merge(['class' => 'notify-search']) }}>
    <label class="notify-visually-hidden" for="{{ $id }}">{{ $label }}</label>
    <x-notify.icon name="search" :size="18" class="notify-search__icon" />
    <input id="{{ $id }}" class="notify-input notify-search__input" type="search" name="{{ $name }}" value="{{ $value }}"
           placeholder="{{ $placeholder ?? $label }}" autocomplete="off" enterkeyhint="search">
    @if(filled($value) && $clearHref)
        <a class="notify-search__clear" href="{{ $clearHref }}" aria-label="{{ __('notify.ui.clear_search') }}" data-search-clear>
            <x-notify.icon name="x" :size="16" />
        </a>
    @endif
</div>
