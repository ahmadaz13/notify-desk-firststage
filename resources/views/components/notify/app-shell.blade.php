@props([
    'title' => 'Notify Desk',
    'locale' => null,
    'direction' => null,
])

@php
    use App\Models\Client;
    use App\Models\User;
    use App\Support\FinancialPermissions;
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
        'partners' => __('notify.navigation.partners'),
        'collections' => __('notify.navigation.collections'),
        'finance' => __('notify.navigation.finance'),
        'expenses' => __('notify.navigation.expenses'),
        'capital_management' => __('notify.navigation.capital_management'),
        'executive' => __('notify.navigation.executive'),
        'saas' => __('notify.navigation.saas'),
        'import' => __('notify.navigation.import'),
        'conflicts' => __('notify.navigation.conflicts'),
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

    $isWork = request()->routeIs('work') || (request()->routeIs('dashboard') && request()->query('mode') === 'work');
    $isTodayArea = (request()->routeIs('dashboard') && ! in_array(request()->query('mode'), ['financial'], true)) || request()->routeIs('work');
    $isTodayTab = $isTodayArea && ! $isWork;
    $isClients = request()->routeIs('clients.*') && ! request()->routeIs('clients.import*');

    $isFinance = request()->routeIs('finance.*')
        || request()->routeIs('collections.*')
        || request()->routeIs('operating-expenses.*')
        || request()->routeIs('capital-management.*')
        || request()->routeIs('subscription-billing.*')
        || request()->routeIs('executive.*')
        || request()->routeIs('saas-metrics.*');

    $isAdministration = request()->routeIs('commercial-catalog.*')
        || request()->routeIs('partners.*')
        || request()->routeIs('settings.*')
        || request()->routeIs('conflicts.*')
        || request()->routeIs('clients.import*');

    $canViewFinance = auth()->check() && ! $isStaff && ($allows(FinancialPermissions::VIEW_FINANCIAL_STATEMENTS) || $allows(FinancialPermissions::VIEW_FINANCIAL_REPORTS) || $admin);
    $canViewAdministration = auth()->check() && ! $isStaff && ($admin || $allows(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG) || $allows(FinancialPermissions::MANAGE_FINANCIAL_SETTINGS));

    $financeSubLinks = [];
    if ($canViewFinance) {
        if ($allows(FinancialPermissions::VIEW_FINANCIAL_REPORTS)) {
            $financeSubLinks[] = [
                'id' => 'collections',
                'label' => $labels['collections'],
                'href' => route('collections.index'),
                'icon' => 'wallet',
                'active' => $isRoute(['collections.*']),
            ];
        }
        if ($allows(FinancialPermissions::VIEW_EXPENSE_MANAGEMENT)) {
            $financeSubLinks[] = [
                'id' => 'operating-expenses',
                'label' => $labels['expenses'],
                'href' => route('operating-expenses.index'),
                'icon' => 'clipboard-list',
                'active' => $isRoute(['operating-expenses.*', 'expense-categories.*', 'vendors.*', 'recurring-expense-*']),
            ];
        }
        if ($allows(FinancialPermissions::VIEW_CAPITAL_MANAGEMENT)) {
            $financeSubLinks[] = [
                'id' => 'capital-management',
                'label' => $labels['capital_management'],
                'href' => route('capital-management.index'),
                'icon' => 'landmark',
                'active' => $isRoute(['capital-management.*', 'funding-sources.*', 'capital-funding-transactions.*', 'asset-categories.*', 'fixed-assets.*']),
            ];
        }
        if ($allows(FinancialPermissions::RUN_SUBSCRIPTION_BILLING)) {
            $financeSubLinks[] = [
                'id' => 'subscription-management',
                'label' => $labels['subscription_management'],
                'href' => route('subscription-billing.index'),
                'icon' => 'briefcase',
                'active' => $isRoute(['subscription-billing.*']),
            ];
        }
        if ($allows(FinancialPermissions::VIEW_EXECUTIVE_DASHBOARD)) {
            $financeSubLinks[] = [
                'id' => 'executive',
                'label' => $labels['executive'],
                'href' => route('executive.index'),
                'icon' => 'chart',
                'active' => $isRoute(['executive.*']),
            ];
        }
        if ($allows(FinancialPermissions::VIEW_SAAS_METRICS)) {
            $financeSubLinks[] = [
                'id' => 'saas-metrics',
                'label' => $labels['saas'],
                'href' => route('saas-metrics.index'),
                'icon' => 'activity',
                'active' => $isRoute(['saas-metrics.*']),
            ];
        }
    }

    $adminSubLinks = [];
    if ($canViewAdministration) {
        if ($allows(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG)) {
            $adminSubLinks[] = [
                'id' => 'products-pricing',
                'label' => $labels['products_pricing'],
                'href' => route('commercial-catalog.index'),
                'icon' => 'package',
                'active' => $isRoute(['commercial-catalog.*']),
            ];
        }
        if ($admin) {
            $adminSubLinks[] = [
                'id' => 'partners',
                'label' => $labels['partners'],
                'href' => route('partners.index'),
                'icon' => 'building',
                'active' => $isRoute(['partners.*']),
            ];
        }
        if ($canCreateClient) {
            $adminSubLinks[] = [
                'id' => 'import',
                'label' => $labels['import'],
                'href' => route('clients.import'),
                'icon' => 'clipboard-list',
                'active' => $isRoute(['clients.import*']),
            ];
        }
        if ($admin) {
            $adminSubLinks[] = [
                'id' => 'conflicts',
                'label' => $labels['conflicts'],
                'href' => route('conflicts.index'),
                'icon' => 'activity',
                'active' => $isRoute(['conflicts.*']),
            ];
        }
        if ($allows(FinancialPermissions::MANAGE_FINANCIAL_SETTINGS)) {
            $adminSubLinks[] = [
                'id' => 'settings',
                'label' => $labels['settings'],
                'href' => route('settings.index'),
                'icon' => 'settings',
                'active' => $isRoute(['settings.*']),
            ];
        }
    }

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
        $activeSub = collect($financeSubLinks)->first(fn ($l) => $l['active']);
        $pageTitle = $activeSub ? $activeSub['label'] : $labels['finance'];
    } elseif ($isAdministration) {
        $pageContextLabel = $labels['administration'];
        $activeSub = collect($adminSubLinks)->first(fn ($l) => $l['active']);
        $pageTitle = $activeSub ? $activeSub['label'] : $labels['administration'];
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
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('notify_sidebar_collapsed', this.sidebarCollapsed.toString());
        }
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
                                <div class="notify-nav-area notify-nav-area--finance" data-nav-area="finance">
                                    <x-notify.nav-item data-nav-destination="finance" :href="route('finance.index')" :label="$labels['finance']" icon="chart" :active="$isFinance" />
                                    @if(!empty($financeSubLinks))
                                        <div class="notify-nav__sub">
                                            @foreach($financeSubLinks as $link)
                                                <a
                                                    class="notify-nav__sub-link {{ $link['active'] ? 'is-active' : '' }}"
                                                    href="{{ $link['href'] }}"
                                                    data-nav-destination="{{ $link['id'] }}"
                                                    @if($link['active']) aria-current="page" @endif
                                                >
                                                    <x-notify.icon :name="$link['icon']" :size="14" />
                                                    <span>{{ $link['label'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Area 4: Administration --}}
                            @if($canViewAdministration)
                                <div class="notify-nav-area notify-nav-area--admin" data-nav-area="administration">
                                    <x-notify.nav-item data-nav-destination="administration" :href="route('commercial-catalog.index')" :label="$labels['administration']" icon="settings" :active="$isAdministration" />
                                    @if(!empty($adminSubLinks))
                                        <div class="notify-nav__sub">
                                            @foreach($adminSubLinks as $link)
                                                <a
                                                    class="notify-nav__sub-link {{ $link['active'] ? 'is-active' : '' }}"
                                                    href="{{ $link['href'] }}"
                                                    data-nav-destination="{{ $link['id'] }}"
                                                    @if($link['active']) aria-current="page" @endif
                                                >
                                                    <x-notify.icon :name="$link['icon']" :size="14" />
                                                    <span>{{ $link['label'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
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
                        <x-notify.button data-shell-action="notifications" :href="route('notifications.index')" variant="ghost" icon="bell" hide-label-on-mobile="true" aria-label="{{ $labels['open_notifications'] }}">
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
            :can-view-clients="$canViewClients"
            :can-view-finance="$canViewFinance"
            :can-view-administration="$canViewAdministration"
            :is-staff="$isStaff"
            :is-today="$isTodayArea"
            :is-clients="$isClients"
            :is-finance="$isFinance"
            :is-administration="$isAdministration"
            :finance-sub-links="$financeSubLinks"
            :admin-sub-links="$adminSubLinks"
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
