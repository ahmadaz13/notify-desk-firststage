@props([
    'title' => 'Notify Desk',
    'locale' => null,
    'direction' => null,
])

@php
    use App\Models\Client;
    use App\Models\User;
    use App\Support\Permissions;
    use Illuminate\Support\Facades\Gate;

    $appLocale = $locale ?? str_replace('_', '-', app()->getLocale());
    $language = strtolower(str($appLocale)->before('-')->toString());
    $direction = $direction ?? (in_array($language, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr');

    $labels = [
        'app' => __('notify.navigation.app'),
        'workspace' => __('notify.navigation.workspace'),
        'daily' => __('notify.navigation.daily'),
        'management' => __('notify.navigation.management'),
        'commercial' => __('notify.navigation.commercial'),
        'money' => __('notify.navigation.money'),
        'reports_group' => __('notify.navigation.reports_group'),
        'operations_admin' => __('notify.navigation.operations_admin'),
        'system' => __('notify.navigation.system'),
        'advanced' => __('notify.navigation.advanced'),
        'account' => __('notify.navigation.account'),
        'primary_navigation' => __('notify.navigation.primary_navigation'),
        'management_navigation' => __('notify.navigation.management_navigation'),
        'advanced_navigation' => __('notify.navigation.advanced_navigation'),
        'mobile_navigation' => __('notify.navigation.mobile_navigation'),
        'today' => __('notify.navigation.today'),
        'clients' => __('notify.navigation.clients'),
        'work' => __('notify.navigation.work'),
        'more' => __('notify.navigation.more'),
        'subscription_management' => __('notify.navigation.subscription_management'),
        'products_pricing' => __('notify.navigation.products_pricing'),
        'collections' => __('notify.navigation.collections'),
        'finance' => __('notify.navigation.finance'),
        'expenses' => __('notify.navigation.expenses'),
        'capital_management' => __('notify.navigation.capital_management'),
        'executive' => __('notify.navigation.executive'),
        'saas' => __('notify.navigation.saas'),
        'import' => __('notify.navigation.import'),
        'settings' => __('notify.navigation.settings'),
        'administration' => __('notify.navigation.administration') ?: 'Administration',
        'financial_accounts' => __('notify.navigation.financial_accounts'),
        'accounting' => __('notify.navigation.accounting'),
        'notifications' => __('notify.navigation.notifications'),
        'add_client' => __('notify.actions.add_client'),
        'logout' => __('notify.actions.logout'),
        'close' => __('notify.actions.close'),
        'open_notifications' => __('notify.actions.open_notifications'),
        'collapse_sidebar' => __('notify.shell.collapse_sidebar') ?: 'Collapse sidebar',
        'expand_sidebar' => __('notify.shell.expand_sidebar') ?: 'Expand sidebar',
        'theme' => __('notify.shell.toggle_theme'),
        'profile' => __('notify.navigation.profile'),
        'change_password' => __('notify.profile.change_password'),
    ];

    $unreadNotificationsCount = $unreadCount ?? 0;
    $user = auth()->user();
    $admin = auth()->check() && $user->isAdmin();
    $isStaff = auth()->check() && $user->isStaff();
    $roleLabel = auth()->check() ? __($user->roleLabelKey()) : __('notify.common.role_staff');
    $allows = fn (string $permission): bool => auth()->check() && Gate::allows($permission);
    $isRoute = fn (array $patterns): bool => request()->routeIs(...$patterns);

    $canViewClients = auth()->check() && Gate::allows('viewAny', Client::class);
    $canCreateClient = auth()->check() && Gate::allows('create', Client::class);

    $isWork = request()->routeIs('dashboard') && request()->query('mode') === 'work';
    $isTodayArea = request()->routeIs('dashboard');
    $isTodayTab = $isTodayArea && ! $isWork;
    $isClients = (request()->routeIs('clients.*') && ! request()->routeIs('clients.import*')) || request()->routeIs('custom-projects.*');

    $isFinance = request()->routeIs('finance.*')
        || request()->routeIs('collections.*')
        || request()->routeIs('operating-expenses.*')
        || request()->routeIs('capital-management.*')
        || request()->routeIs('subscription-billing.*')
        || request()->routeIs('executive.*')
        || request()->routeIs('saas-metrics.*')
        || request()->routeIs('financial-accounts.*')
        || request()->routeIs('accounting.*');

    $isAdministration = request()->routeIs('commercial-catalog.*')
        || request()->routeIs('administration.*')
        || request()->routeIs('settings.*')
        || request()->routeIs('clients.import*');

    $canViewFinance = auth()->check() && ! $isStaff && ($allows(Permissions::VIEW_FINANCIAL_STATEMENTS) || $allows(Permissions::VIEW_FINANCIAL_REPORTS) || $admin);
    $canViewAdministration = auth()->check() && ! $isStaff && ($admin || $allows(Permissions::MANAGE_COMMERCIAL_CATALOG) || $allows(Permissions::MANAGE_COMPANY_SETTINGS));

    // Determine page context & title
    $pageContextLabel = $labels['workspace'];
    $pageTitle = $labels['app'];
    if ($isTodayArea) {
        $pageContextLabel = $labels['daily'];
        $pageTitle = $isWork ? $labels['work'] : $labels['today'];
    } elseif ($isClients) {
        $pageContextLabel = $labels['clients'];
        $pageTitle = $labels['clients'];
    } elseif ($isFinance) {
        $pageContextLabel = $labels['finance'];
        $pageTitle = $labels['finance'];
    } elseif ($isAdministration) {
        $pageContextLabel = $labels['administration'];
        $pageTitle = $labels['administration'];
    } elseif (request()->routeIs('notifications.*')) {
        $pageContextLabel = $labels['workspace'];
        $pageTitle = $labels['notifications'];
    }

    $showAddClient = $canCreateClient && ($isTodayTab || request()->routeIs('clients.index'));
    $targetLocale = $language === 'ar' ? 'en' : 'ar';
    $targetLocaleLabel = $language === 'ar' ? 'English' : 'العربية';
@endphp

<!doctype html>
<html lang="{{ $appLocale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <script>document.documentElement.dataset.theme=localStorage.getItem('notify_theme')==='dark'?'dark':'light';</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div
    class="notify-shell"
    x-data="{
        sidebarCollapsed: localStorage.getItem('notify_sidebar_collapsed') === 'true',
        profileOpen: false,
        theme: document.documentElement.dataset.theme,
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('notify_sidebar_collapsed', this.sidebarCollapsed.toString());
        },
        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = this.theme;
            localStorage.setItem('notify_theme', this.theme);
        },
    }"
    :class="{ 'notify-shell--collapsed': sidebarCollapsed }"
>
    <div class="notify-shell__body">
        @auth
            <aside class="notify-sidebar" aria-label="{{ $labels['primary_navigation'] }}">
                <div class="notify-sidebar__header">
                    <a class="notify-brand" href="{{ route('dashboard', ['mode' => 'daily']) }}">
                        <img src="{{ asset('brand/notify/notify-logo-light.svg') }}" alt="Notify" class="notify-brand__logo" width="36" height="36">
                        <span class="notify-brand__text">{{ $labels['app'] }}</span>
                    </a>
                </div>

                <div class="notify-sidebar__navigation">
                    {{-- 4 Clean Areas --}}
                    <nav class="notify-nav notify-nav--primary" aria-label="{{ $labels['primary_navigation'] }}">
                        {{-- Area 1: Today --}}
                        <x-notify.nav-item data-nav-destination="today" :href="route('dashboard', ['mode' => 'daily'])" :label="$labels['today']" icon="home" :active="$isTodayArea" />

                        {{-- Area 2: Clients --}}
                        @if($canViewClients)
                            <x-notify.nav-item data-nav-destination="clients" :href="route('clients.index')" :label="$labels['clients']" icon="users" :active="$isClients" />
                        @endif

                        {{-- Staff desktop shows ONLY Today and Clients --}}
                        @if(!$isStaff)
                            {{-- Area 3: Finance --}}
                            @if($canViewFinance)
                                <x-notify.nav-item data-nav-destination="finance" :href="route('finance.index')" :label="$labels['finance']" icon="chart" :active="$isFinance" />
                            @endif

                            {{-- Area 4: Administration --}}
                            @if($canViewAdministration)
                                <x-notify.nav-item data-nav-destination="administration" :href="route('administration.index')" :label="$labels['administration']" icon="settings" :active="$isAdministration" />
                            @endif
                        @endif
                    </nav>
                </div>

                {{-- Sidebar Collapse Toggle & User Footer --}}
                <div class="notify-sidebar__footer">
                    <button
                        type="button"
                        class="notify-sidebar__collapse-toggle"
                        @click="toggleSidebar()"
                        :title="sidebarCollapsed ? '{{ $labels['expand_sidebar'] }}' : '{{ $labels['collapse_sidebar'] }}'"
                        :aria-label="sidebarCollapsed ? '{{ $labels['expand_sidebar'] }}' : '{{ $labels['collapse_sidebar'] }}'"
                    >
                        <x-notify.icon name="panel-left" :size="18" />
                        <span class="notify-sidebar__collapse-text" x-text="sidebarCollapsed ? '{{ $labels['expand_sidebar'] }}' : '{{ $labels['collapse_sidebar'] }}'"></span>
                    </button>

                    <div class="notify-user">
                        <div class="notify-user__main">
                            <span class="notify-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                            <div class="notify-user__identity">
                                <div class="notify-user__name">{{ auth()->user()->name }}</div>
                                <div class="notify-user__role">{{ $roleLabel }}</div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-notify.button variant="ghost" icon="log-out" type="submit" style="width:100%" class="notify-logout-btn">
                                <span class="notify-logout-btn__text">{{ $labels['logout'] }}</span>
                            </x-notify.button>
                        </form>
                    </div>
                </div>
            </aside>
        @endauth

        <div class="notify-main">
            @auth
                <header class="notify-topbar">
                    <div class="notify-topbar__title">
                        <span class="notify-topbar__eyebrow">{{ $pageContextLabel }}</span>
                        <h1 class="notify-topbar__heading">{{ $pageTitle }}</h1>
                    </div>
                    <div class="notify-topbar__actions">
                        @if($showAddClient)
                            <x-notify.button data-shell-action="add-client" :href="route('clients.create')" variant="primary" icon="plus" hide-label-on-mobile="true" aria-label="{{ $labels['add_client'] }}">
                                {{ $labels['add_client'] }}
                            </x-notify.button>
                        @endif
                        <a href="{{ route('locale.switch', $targetLocale) }}" class="notify-locale-switcher" title="{{ __('notify.actions.switch_language') }}" aria-label="{{ __('notify.actions.switch_language') }}">
                            <x-notify.icon name="languages" :size="16" />
                            <span>{{ $targetLocaleLabel }}</span>
                        </a>
                        <button type="button" class="notify-icon-button" @click="toggleTheme()" title="{{ $labels['theme'] }}" aria-label="{{ $labels['theme'] }}">
                            <x-notify.icon name="theme" :size="18" />
                        </button>
                        <x-notify.button data-shell-action="notifications" :href="route('notifications.index')" variant="ghost" icon="bell" hide-label-on-mobile="true" aria-label="{{ $labels['open_notifications'] }}">
                            {{ $labels['notifications'] }}
                            @if($unreadNotificationsCount > 0)
                                <x-notify.badge variant="danger">{{ $unreadNotificationsCount }}</x-notify.badge>
                            @endif
                        </x-notify.button>
                        <div class="notify-profile-menu" @click.outside="profileOpen=false">
                            <button type="button" class="notify-icon-button" @click="profileOpen=!profileOpen" :aria-expanded="profileOpen.toString()" aria-label="{{ $labels['profile'] }}">
                                <x-notify.icon name="user" :size="18" />
                            </button>
                            <div class="notify-profile-menu__panel" x-show="profileOpen" x-cloak>
                                <a href="{{ route('profile.edit') }}">{{ $labels['profile'] }}</a>
                                <a href="{{ route('profile.edit') }}#password">{{ $labels['change_password'] }}</a>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">{{ $labels['logout'] }}</button></form>
                            </div>
                        </div>
                    </div>
                </header>
            @endauth

            <main class="notify-content">
                @if(session('success'))
                    <div class="notify-flash">{{ session('success') }}</div>
                @endif
                @if(session('warning'))
                    <div class="notify-flash notify-flash--error">{{ session('warning') }}</div>
                @endif
                @if($errors->any())
                    <div class="notify-flash notify-flash--error">{{ $errors->first() ?? __('notify.common.review_errors') }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

    @auth
        <x-notify.mobile-nav
            :labels="$labels"
            :can-view-clients="$canViewClients"
            :can-view-finance="$canViewFinance"
            :can-view-administration="$canViewAdministration"
            :is-staff="$isStaff"
            :is-today="$isTodayArea"
            :is-clients="$isClients"
            :is-finance="$isFinance"
            :is-administration="$isAdministration"
            :more-active="$isFinance || $isAdministration"
            :target-locale="$targetLocale"
            :target-locale-label="$targetLocaleLabel"
            :user-name="$user->name"
            :role-label="$roleLabel"
        />
    @endauth
</div>
</body>
</html>
