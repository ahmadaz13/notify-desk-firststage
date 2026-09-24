@props([
    'title' => 'Notify Desk',
    'locale' => null,
    'direction' => null,
    'width' => 'normal',
])

@php
    use App\Support\ShellNavigation;

    $appLocale = $locale ?? str_replace('_', '-', app()->getLocale());
    $language = strtolower(str($appLocale)->before('-')->toString());
    $direction = $direction ?? (in_array($language, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr');

    $user = auth()->user();
    $unreadNotificationsCount = (int) ($unreadCount ?? 0);
    // All visibility comes from the permission matrix via ShellNavigation (§2, §3, P9).
    $nav = $user ? ShellNavigation::forCurrentRequest($user, $unreadNotificationsCount) : null;
    $roleLabel = $user ? __($user->roleLabelKey()) : '';
    $initial = $user ? mb_substr($user->name, 0, 1) : '';

    $targetLocale = $language === 'ar' ? 'en' : 'ar';
    $targetLocaleLabel = $language === 'ar' ? 'English' : 'العربية';
    $contentWidth = in_array($width, ['normal', 'wide'], true) ? $width : 'normal';

    $labels = [
        'main_navigation' => __('notify.shell_nav.main_navigation'),
        'notifications' => __('notify.shell_nav.areas.notifications'),
        'profile' => __('notify.shell_nav.areas.profile'),
        'change_password' => __('notify.shell_nav.change_password'),
        'logout' => __('notify.shell_nav.logout'),
        'account_menu' => __('notify.shell_nav.account_menu'),
        'switch_language' => __('notify.actions.switch_language'),
        'collapse_sidebar' => __('notify.shell.collapse_sidebar'),
        'expand_sidebar' => __('notify.shell.expand_sidebar'),
        'skip_to_content' => __('notify.shell_nav.skip_to_content'),
    ];
    $notificationsLabel = $unreadNotificationsCount > 0
        ? $labels['notifications'].' · '.__('notify.shell_nav.unread_badge', ['count' => $unreadNotificationsCount])
        : $labels['notifications'];
@endphp

<!doctype html>
<html lang="{{ $appLocale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <title>{{ $title }}</title>
    @include('partials.pwa-head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="notify-skip-link" href="#notify-main-content">{{ $labels['skip_to_content'] }}</a>
<div
    class="notify-shell"
    x-data="{
        sidebarCollapsed: localStorage.getItem('notify_sidebar_collapsed') === 'true',
        profileOpen: false,
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('notify_sidebar_collapsed', this.sidebarCollapsed.toString());
        },
    }"
    :class="{ 'notify-shell--collapsed': sidebarCollapsed }"
>
    <div class="notify-shell__body">
        @if($nav)
            <aside class="notify-sidebar" aria-label="{{ $labels['main_navigation'] }}" data-shell-sidebar>
                <div class="notify-sidebar__header">
                    <a class="notify-brand" href="{{ route('dashboard', ['mode' => 'daily']) }}">
                        <img src="{{ asset('brand/notify/notify-logo-light.svg') }}" alt="" class="notify-brand__logo" width="32" height="32">
                        <span class="notify-brand__text">{{ __('notify.shell_nav.app') }}</span>
                    </a>
                </div>

                <div class="notify-sidebar__navigation">
                    <nav class="notify-nav notify-nav--primary" aria-label="{{ $labels['main_navigation'] }}">
                        @foreach($nav->primary as $item)
                            <x-notify.nav-item :item="$item" />
                        @endforeach
                    </nav>

                    @foreach($nav->groups as $group)
                        <section class="notify-nav-section {{ $group['active'] ? 'is-active' : '' }}" data-nav-group="{{ $group['key'] }}" aria-labelledby="notify-nav-group-{{ $group['key'] }}">
                            <h2 class="notify-nav-section__label" id="notify-nav-group-{{ $group['key'] }}">{{ $group['label'] }}</h2>
                            <nav class="notify-nav" aria-labelledby="notify-nav-group-{{ $group['key'] }}">
                                @foreach($group['items'] as $item)
                                    <x-notify.nav-item :item="$item" />
                                @endforeach
                            </nav>
                        </section>
                    @endforeach
                </div>

                <div class="notify-sidebar__footer">
                    <button
                        type="button"
                        class="notify-sidebar__collapse-toggle"
                        @click="toggleSidebar()"
                        :aria-pressed="sidebarCollapsed.toString()"
                        :title="sidebarCollapsed ? @js($labels['expand_sidebar']) : @js($labels['collapse_sidebar'])"
                        :aria-label="sidebarCollapsed ? @js($labels['expand_sidebar']) : @js($labels['collapse_sidebar'])"
                    >
                        <x-notify.icon name="panel-left" :size="18" class="notify-icon--directional" />
                        <span class="notify-sidebar__collapse-text" x-text="sidebarCollapsed ? @js($labels['expand_sidebar']) : @js($labels['collapse_sidebar'])">{{ $labels['collapse_sidebar'] }}</span>
                    </button>

                    <a class="notify-user" href="{{ route('profile.edit') }}" title="{{ $labels['profile'] }}" data-shell-user>
                        <span class="notify-avatar" aria-hidden="true">{{ $initial }}</span>
                        <span class="notify-user__identity">
                            <span class="notify-user__name">{{ $user->name }}</span>
                            <span class="notify-user__role">{{ $roleLabel }}</span>
                        </span>
                    </a>
                </div>
            </aside>
        @endif

        <div class="notify-main">
            @if($nav)
                <header class="notify-topbar">
                    <div class="notify-topbar__context">
                        <a class="notify-topbar__brand" href="{{ route('dashboard', ['mode' => 'daily']) }}" aria-label="{{ __('notify.shell_nav.app') }}">
                            <img src="{{ asset('brand/notify/notify-logo-light.svg') }}" alt="" width="28" height="28">
                        </a>
                        <p class="notify-topbar__area" data-shell-area="{{ $nav->area ?? 'app' }}">{{ $nav->areaLabel }}</p>
                    </div>
                    <div class="notify-topbar__actions">
                        <a href="{{ route('locale.switch', $targetLocale) }}" class="notify-topbar__control notify-locale-switcher" lang="{{ $targetLocale }}" title="{{ $labels['switch_language'] }}" aria-label="{{ $labels['switch_language'] }}: {{ $targetLocaleLabel }}">
                            <x-notify.icon name="languages" :size="18" />
                            <span class="notify-topbar__control-text">{{ $targetLocaleLabel }}</span>
                        </a>
                        <a href="{{ route('notifications.index') }}" class="notify-topbar__control notify-topbar__control--icon {{ $nav->area === 'notifications' ? 'is-active' : '' }}" data-shell-action="notifications" title="{{ $labels['notifications'] }}" aria-label="{{ $notificationsLabel }}" @if($nav->area === 'notifications') aria-current="page" @endif>
                            <x-notify.icon name="bell" :size="18" />
                            @if($unreadNotificationsCount > 0)
                                <span class="notify-count-badge" aria-hidden="true">{{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}</span>
                            @endif
                        </a>
                        <div class="notify-profile-menu" @click.outside="profileOpen = false" @keydown.escape="if (profileOpen) { profileOpen = false; $refs.profileButton.focus(); }">
                            <button type="button" class="notify-topbar__control notify-topbar__control--icon notify-profile-menu__button" x-ref="profileButton" @click="profileOpen = !profileOpen" :aria-expanded="profileOpen.toString()" aria-expanded="false" aria-controls="notify-profile-menu" aria-label="{{ $labels['account_menu'] }}" title="{{ $labels['account_menu'] }}">
                                <span class="notify-avatar notify-avatar--sm" aria-hidden="true">{{ $initial }}</span>
                            </button>
                            <div class="notify-profile-menu__panel" id="notify-profile-menu" x-show="profileOpen" x-cloak x-transition.opacity.duration.120ms>
                                <div class="notify-profile-menu__identity">
                                    <span class="notify-user__name">{{ $user->name }}</span>
                                    <span class="notify-user__role">{{ $roleLabel }}</span>
                                </div>
                                <a href="{{ route('profile.edit') }}"><x-notify.icon name="user" :size="16" /><span>{{ $labels['profile'] }}</span></a>
                                <a href="{{ route('profile.edit') }}#password"><x-notify.icon name="key-round" :size="16" /><span>{{ $labels['change_password'] }}</span></a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"><x-notify.icon name="log-out" :size="16" class="notify-icon--directional" /><span>{{ $labels['logout'] }}</span></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>
            @endif

            {{-- Connection feedback (P9.1): transient, never implies data was saved locally. --}}
            <div
                class="notify-connection-status"
                role="status"
                aria-live="polite"
                x-data="{ offline: ! navigator.onLine, restored: false, timer: null }"
                @offline.window="offline = true; restored = false; clearTimeout(timer)"
                @online.window="offline = false; restored = true; clearTimeout(timer); timer = setTimeout(() => restored = false, 3000)"
                data-connection-status
            >
                <p class="notify-connection-status__pill notify-connection-status__pill--offline" x-show="offline" x-cloak>{{ __('notify.pwa.connection.offline') }}</p>
                <p class="notify-connection-status__pill notify-connection-status__pill--online" x-show="restored" x-cloak>{{ __('notify.pwa.connection.online') }}</p>
            </div>

            <main id="notify-main-content" class="notify-content notify-content--{{ $contentWidth }}" tabindex="-1">
                @if(session('success'))
                    <div class="notify-flash" role="status">{{ session('success') }}</div>
                @endif
                @if(session('warning'))
                    <div class="notify-flash notify-flash--error" role="alert">{{ session('warning') }}</div>
                @endif
                @if($errors->any())
                    <div class="notify-flash notify-flash--error" role="alert">{{ $errors->first() ?? __('notify.common.review_errors') }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

    @if($nav)
        <x-notify.mobile-nav
            :nav="$nav"
            :target-locale="$targetLocale"
            :target-locale-label="$targetLocaleLabel"
            :user-name="$user->name"
            :role-label="$roleLabel"
        />
    @endif
</div>
</body>
</html>
