@props([
    'title',
    'description' => null,
])

{{--
    Page header pattern (P9): one h1 per page, optional one-line description, then actions.
    Put at most one primary action in `actions`; secondary actions use quieter variants.
--}}
<header {{ $attributes->merge(['class' => 'notify-page-header']) }} data-page-header>
    <div class="notify-page-header__text">
        <h1 class="notify-page-header__title">{{ $title }}</h1>
        @if($description)
            <p class="notify-page-header__description">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="notify-page-header__actions">{{ $actions }}</div>
    @endisset
</header>
