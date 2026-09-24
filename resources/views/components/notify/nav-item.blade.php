@props([
    'item',
])

{{-- One navigation destination (P9). $item comes from App\Support\ShellNavigation. --}}
<a {{ $attributes->merge(['class' => 'notify-nav-item'.($item['active'] ? ' is-active' : '')]) }} href="{{ $item['href'] }}" data-nav-destination="{{ $item['destination'] }}" title="{{ $item['label'] }}" @if($item['active']) aria-current="page" @endif>
    <span class="notify-nav-item__icon">
        <x-notify.icon :name="$item['icon']" />
    </span>
    <span class="notify-nav-item__text">{{ $item['label'] }}</span>
    @if($item['badge'])
        <span class="notify-count-badge notify-nav-item__badge" aria-hidden="true">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
        <span class="notify-visually-hidden">{{ __('notify.shell_nav.pending_badge', ['count' => $item['badge']]) }}</span>
    @endif
</a>
