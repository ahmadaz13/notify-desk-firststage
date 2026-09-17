@props([
    'title' => 'Notify Desk',
    'locale' => null,
    'direction' => null,
])

@php
    $appLocale = $locale ?? str_replace('_', '-', app()->getLocale());
    $language = strtolower(str($appLocale)->before('-')->toString());
    $direction = $direction ?? (in_array($language, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr');
    $isArabic = ($language === 'ar');

    // Authoritative dynamic translation with fallback support
    $labels = [
        'app' => __('notify.navigation.app'),
        'workspace' => __('notify.navigation.workspace'),
        'primary_navigation' => __('notify.navigation.primary_navigation'),
        'secondary_navigation' => __('notify.navigation.secondary_navigation'),
        'mobile_navigation' => __('notify.navigation.mobile_navigation'),
        'today' => __('notify.navigation.today'),
        'clients' => __('notify.navigation.clients'),
        'sales' => __('notify.navigation.sales'),
        'finance' => __('notify.navigation.finance'),
        'reports' => __('notify.navigation.reports'),
        'administration' => __('notify.navigation.administration'),
        'advanced' => __('notify.navigation.advanced'),
        'accounting' => __('notify.navigation.accounting'),
        'notifications' => __('notify.navigation.notifications'),
        'collections' => __('notify.navigation.collections'),
        'catalog' => __('notify.navigation.catalog'),
        'import' => __('notify.navigation.import'),
        'review_work' => __('notify.navigation.review_work'),
        'settings' => __('notify.navigation.settings'),
        'work' => __('notify.navigation.work'),
        'more' => __('notify.navigation.more'),
        'add_client' => __('notify.actions.add_client'),
        'add_client_short' => __('notify.actions.add_client_short'),
        'logout' => __('notify.actions.logout'),
        'role_founder' => __('notify.common.role_founder'),
        'role_admin' => __('notify.common.role_admin'),
        'role_staff' => __('notify.common.role_staff'),
        'open_notifications' => __('notify.actions.open_notifications'),
    ];

    $unreadNotificationsCount = $unreadCount ?? 0;

    $user = auth()->user();
    $admin = auth()->check() && $user->isAdmin();
    $roleLabel = auth()->check() ? __($user->roleLabelKey()) : $labels['role_staff'];
    $canViewClients = auth()->check() && \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Client::class);
    $canCreateClient = auth()->check() && \Illuminate\Support\Facades\Gate::allows('create', \App\Models\Client::class);
    $canRunSubscriptionBilling = auth()->check() && \Illuminate\Support\Facades\Gate::allows(\App\Support\FinancialPermissions::RUN_SUBSCRIPTION_BILLING);
    $canViewFinance = auth()->check() && \Illuminate\Support\Facades\Gate::allows(\App\Support\FinancialPermissions::VIEW_FINANCIAL_STATEMENTS);
    $canViewExecutive = auth()->check() && \Illuminate\Support\Facades\Gate::allows(\App\Support\FinancialPermissions::VIEW_EXECUTIVE_DASHBOARD);
    $canViewCollections = auth()->check() && \Illuminate\Support\Facades\Gate::allows(\App\Support\FinancialPermissions::VIEW_FINANCIAL_REPORTS);
    $canManageCatalog = auth()->check() && \Illuminate\Support\Facades\Gate::allows(\App\Support\FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);
    $canManageSettings = auth()->check() && \Illuminate\Support\Facades\Gate::allows(\App\Support\FinancialPermissions::MANAGE_FINANCIAL_SETTINGS);
    $canViewAccounting = auth()->check() && \Illuminate\Support\Facades\Gate::allows(\App\Support\FinancialPermissions::VIEW_ACCOUNTING);
    $canReviewConflicts = $admin;
    $canImportClients = $canCreateClient;
    $targetLocale = $language === 'ar' ? 'en' : 'ar';
    $targetLocaleLabel = $language === 'ar' ? 'English' : 'العربية';
@endphp

<!doctype html>
<html lang="{{ $appLocale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="notify-shell">
    <div class="notify-shell__body">
        @auth
            <aside class="notify-sidebar" aria-label="{{ $labels['primary_navigation'] }}">
                <a class="notify-brand" href="{{ route('dashboard', ['mode' => 'daily']) }}">
                    <img src="{{ asset('brand/notify/notify-logo-light.svg') }}" alt="Notify" class="notify-brand__logo" width="36" height="36">
                    <span>{{ $labels['app'] }}</span>
                </a>

                <nav class="notify-nav">
                    <x-notify.nav-item :href="route('dashboard', ['mode' => 'daily'])" :label="$labels['today']" icon="home" :active="request()->routeIs('dashboard') && ! in_array(request()->query('mode'), ['work', 'financial'], true)" />
                    @if($canViewClients)
                        <x-notify.nav-item :href="route('clients.index')" :label="$labels['clients']" icon="users" :active="request()->routeIs('clients.*')" />
                    @endif
                    @if($canRunSubscriptionBilling)
                        <x-notify.nav-item :href="route('subscription-billing.index')" :label="$labels['sales']" icon="briefcase" :active="request()->routeIs('subscription-billing.*') || request()->routeIs('collections.*')" />
                    @endif
                    @if($canViewFinance)
                        <x-notify.nav-item :href="route('finance.index')" :label="$labels['finance']" icon="wallet" :active="request()->routeIs('finance.*') || request()->routeIs('financial-accounts.*') || request()->routeIs('financial-transfers.*') || request()->routeIs('operating-expenses.*') || request()->routeIs('capital-management.*') || request()->routeIs('funding-sources.*') || request()->routeIs('capital-funding-transactions.*') || request()->routeIs('asset-categories.*') || request()->routeIs('fixed-assets.*')" />
                    @endif
                    @if($canViewExecutive)
                        <x-notify.nav-item :href="route('executive.index')" :label="$labels['reports']" icon="chart" :active="request()->routeIs('executive.*') || request()->routeIs('saas-metrics.*')" />
                    @endif
                </nav>

                @if($canManageSettings || $canViewAccounting)
                    <div class="notify-nav__section">
                        <p class="notify-nav__label">{{ $labels['secondary_navigation'] }}</p>
                        <nav class="notify-nav" aria-label="{{ $labels['secondary_navigation'] }}">
                            @if($canManageSettings)
                                <x-notify.nav-item :href="route('settings.index')" :label="$labels['administration']" icon="settings" :active="request()->routeIs('settings.*') || request()->routeIs('commercial-catalog.*') || request()->routeIs('clients.import*') || request()->routeIs('conflicts.*') || request()->routeIs('partners.*')" />
                            @endif
                            @if($canViewAccounting)
                                <x-notify.nav-item :href="route('accounting.index')" :label="$labels['advanced'].' '.$labels['accounting']" icon="landmark" :active="request()->routeIs('accounting.*')" />
                            @endif
                        </nav>
                    </div>
                @endif

                <div class="notify-sidebar__footer">
                    <div class="notify-user">
                        <div class="notify-user__main">
                            <span class="notify-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                            <div style="min-width:0">
                                <div class="notify-user__name">{{ auth()->user()->name }}</div>
                                <div class="notify-user__role">{{ $roleLabel }}</div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-notify.button variant="ghost" icon="log-out" type="submit" style="width:100%">{{ $labels['logout'] }}</x-notify.button>
                        </form>
                    </div>
                </div>
            </aside>
        @endauth

        <div class="notify-main">
            @auth
                <header class="notify-topbar">
                    <div class="notify-topbar__title">
                        <span class="notify-topbar__eyebrow">{{ $labels['workspace'] }}</span>
                        <h1 class="notify-topbar__heading">{{ $labels['app'] }}</h1>
                    </div>
                    <div class="notify-topbar__actions">
                        <a href="{{ route('locale.switch', $targetLocale) }}" class="notify-locale-switcher" title="{{ __('notify.actions.switch_language') }}">
                            <x-notify.icon name="languages" :size="16" />
                            <span>{{ $targetLocaleLabel }}</span>
                        </a>
                        <x-notify.button :href="route('notifications.index')" variant="ghost" icon="bell" hide-label-on-mobile="true" aria-label="{{ $labels['open_notifications'] }}">
                            {{ $labels['notifications'] }}
                            @if($unreadNotificationsCount > 0)
                                <x-notify.badge variant="danger">{{ $unreadNotificationsCount }}</x-notify.badge>
                            @endif
                        </x-notify.button>
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
                    <div class="notify-flash notify-flash--error">{{ $errors->first() ?? 'Please review the submitted information.' }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

    @auth
        <x-notify.mobile-nav
            :labels="$labels"
            :unread-notifications-count="$unreadNotificationsCount"
            :can-view-clients="$canViewClients"
            :can-import-clients="$canImportClients"
            :can-view-collections="$canViewCollections"
            :can-manage-catalog="$canManageCatalog"
            :can-review-conflicts="$canReviewConflicts"
            :can-manage-settings="$canManageSettings"
            :can-view-accounting="$canViewAccounting"
        />
        @if($canCreateClient && (request()->routeIs('dashboard') || request()->routeIs('clients.index')))
            <x-notify.add-client-fab :label="$labels['add_client']" :short-label="$labels['add_client_short']" />
        @endif
    @endauth
</div>
</body>
</html>
