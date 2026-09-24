@props([
    'user',
    'size' => 'md',
])

{{-- Team/profile avatar (P13): the profile photo when one is set, otherwise the name's initial. --}}
@php($url = $user?->avatarUrl())
<span {{ $attributes->class(['notify-avatar', 'notify-avatar--'.$size, 'notify-avatar--photo' => $url]) }} aria-hidden="true">
    @if($url)
        <img src="{{ $url }}" alt="" loading="lazy" decoding="async" width="96" height="96">
    @else
        {{ $user?->initial() }}
    @endif
</span>
