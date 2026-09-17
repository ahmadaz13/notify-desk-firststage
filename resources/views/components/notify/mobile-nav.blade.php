@props([
    'labels',
    'unreadNotificationsCount' => 0,
    'canViewClients' => false,
    'canImportClients' => false,
    'canViewCollections' => false,
    'canManageCatalog' => false,
    'canReviewConflicts' => false,
    'canManageSettings' => false,
    'canViewAccounting' => false,
])

@php
    $secondaryLinks = [
        ['label' => $labels['notifications'], 'href' => route('notifications.index'), 'icon' => 'bell'],
    ];

    if ($canImportClients) {
        $secondaryLinks[] = ['label' => $labels['import'], 'href' => route('clients.import'), 'icon' => 'clipboard-list'];
    }

    if ($canViewCollections) {
        $secondaryLinks[] = ['label' => $labels['collections'], 'href' => route('collections.index'), 'icon' => 'wallet'];
    }

    if ($canManageCatalog) {
        $secondaryLinks[] = ['label' => $labels['catalog'], 'href' => route('commercial-catalog.index'), 'icon' => 'package'];
    }

    if ($canReviewConflicts) {
        $secondaryLinks[] = ['label' => $labels['review_work'], 'href' => route('conflicts.index'), 'icon' => 'activity'];
    }

    if ($canManageSettings) {
        $secondaryLinks[] = ['label' => $labels['settings'], 'href' => route('settings.index'), 'icon' => 'settings'];
    }

    if ($canViewAccounting) {
        $secondaryLinks[] = ['label' => $labels['accounting'], 'href' => route('accounting.index'), 'icon' => 'landmark'];
    }
@endphp

<nav class="notify-mobile-nav" aria-label="{{ $labels['mobile_navigation'] }}" x-data="{ moreOpen: false }" @keydown.escape.window="moreOpen = false">
    <div class="notify-mobile-nav__grid">
        <a class="notify-mobile-nav__item {{ request()->routeIs('dashboard') && ! in_array(request()->query('mode'), ['work', 'financial'], true) ? 'is-active' : '' }}" href="{{ route('dashboard', ['mode' => 'daily']) }}">
            <x-notify.icon name="home" />
            <span class="notify-mobile-nav__label">{{ $labels['today'] }}</span>
        </a>
        @if($canViewClients)
            <a class="notify-mobile-nav__item {{ request()->routeIs('clients.index') || request()->routeIs('clients.show') || request()->routeIs('clients.create') || request()->routeIs('clients.edit') ? 'is-active' : '' }}" href="{{ route('clients.index') }}">
                <x-notify.icon name="users" />
                <span class="notify-mobile-nav__label">{{ $labels['clients'] }}</span>
            </a>
        @else
            <span class="notify-mobile-nav__item" aria-disabled="true">
                <x-notify.icon name="users" />
                <span class="notify-mobile-nav__label">{{ $labels['clients'] }}</span>
            </span>
        @endif
        <a class="notify-mobile-nav__item {{ request()->routeIs('dashboard') && request()->query('mode') === 'work' ? 'is-active' : '' }}" href="{{ route('dashboard', ['mode' => 'work']) }}#mobile-work">
            <x-notify.icon name="briefcase" />
            <span class="notify-mobile-nav__label">{{ $labels['work'] }}</span>
        </a>
        <button class="notify-mobile-nav__item {{ request()->routeIs('notifications.*') || request()->routeIs('settings.*') || request()->routeIs('commercial-catalog.*') || request()->routeIs('accounting.*') || request()->routeIs('collections.*') || request()->routeIs('clients.import*') || request()->routeIs('conflicts.*') ? 'is-active' : '' }}" type="button" @click="moreOpen = !moreOpen" :aria-expanded="moreOpen.toString()">
            <x-notify.icon name="menu" />
            <span class="notify-mobile-nav__label">{{ $labels['more'] }}</span>
        </button>
    </div>

    <div class="notify-mobile-nav__more" x-show="moreOpen" x-cloak @click.outside="moreOpen = false">
        @foreach($secondaryLinks as $link)
            <a class="notify-mobile-nav__more-link" href="{{ $link['href'] }}">
                <span>{{ $link['label'] }}</span>
                <x-notify.icon :name="$link['icon']" />
            </a>
        @endforeach
    </div>
</nav>
