@props([
    'label' => 'Add Client',
    'shortLabel' => 'Add',
])

<a
    class="notify-add-client-fab"
    href="{{ route('clients.create') }}"
    aria-label="{{ $label }}"
    title="{{ $label }}"
    data-notify-add-client-fab
>
    <x-notify.icon name="plus" />
    <span>{{ $shortLabel }}</span>
</a>
