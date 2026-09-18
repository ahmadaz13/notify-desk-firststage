@props([
    'title' => 'Notify Desk',
    'locale' => null,
    'direction' => null,
])

@php
    use App\Models\Client;
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
        'financial_accounts' => __('notify.navigation.financial_accounts'),
        'accounting' => __('notify.navigation.accounting'),
        'notifications' => __('notify.navigation.notifications'),
        'add_client' => __('notify.actions.add_client'),
        'logout' => __('notify.actions.logout'),
        'close' => __('notify.actions.close'),
        'open_notifications' => __('notify.actions.open_notifications'),
    ];

    $unreadNotificationsCount = $unreadCount ?? 0;
    $user = auth()->user();
    $admin = auth()->check() && $user->isAdmin();
    $roleLabel = auth()->check() ? __($user->roleLabelKey()) : __('notify.common.role_staff');
    $allows = fn (string $permission): bool => auth()->check() && Gate::allows($permission);
    $isRoute = fn (array $patterns): bool => request()->routeIs(...$patterns);

    $canViewClients = auth()->check() && Gate::allows('viewAny', Client::class);
    $canCreateClient = auth()->check() && Gate::allows('create', Client::class);
    $isWork = request()->routeIs('work') || (request()->routeIs('dashboard') && request()->query('mode') === 'work');
    $isToday = request()->routeIs('dashboard') && ! in_array(request()->query('mode'), ['work', 'financial'], true) && ! request()->routeIs('work');
    $isClients = request()->routeIs('clients.*') && ! request()->routeIs('clients.import*');

    $managementGroups = [];

    $commercialLinks = [];
    if ($allows(FinancialPermissions::RUN_SUBSCRIPTION_BILLING)) {
        $commercialLinks[] = [
            'id' => 'subscription-management',
            'label' => $labels['subscription_management'],
            'href' => route('subscription-billing.index'),
            'icon' => 'briefcase',
            'active' => $isRoute(['subscription-billing.*']),
        ];
    }
    if ($allows(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG)) {
        $commercialLinks[] = [
            'id' => 'products-pricing',
            'label' => $labels['products_pricing'],
            'href' => route('commercial-catalog.index'),
            'icon' => 'package',
            'active' => $isRoute(['commercial-catalog.*']),
        ];
    }
    if ($admin) {
        $commercialLinks[] = [
            'id' => 'partners',
            'label' => $labels['partners'],
            'href' => route('partners.index'),
            'icon' => 'building',
            'active' => $isRoute(['partners.*']),
        ];
    }
    if ($commercialLinks !== []) {
        $managementGroups[] = [
            'id' => 'commercial',
            'label' => $labels['commercial'],
            'icon' => 'briefcase',
            'links' => $commercialLinks,
            'active' => collect($commercialLinks)->contains('active', true),
        ];
    }

    $moneyLinks = [];
    if ($allows(FinancialPermissions::VIEW_FINANCIAL_REPORTS)) {
        $moneyLinks[] = [
            'id' => 'collections',
            'label' => $labels['collections'],
            'href' => route('collections.index'),
            'icon' => 'wallet',
            'active' => $isRoute(['collections.*']),
        ];
    }
    if ($allows(FinancialPermissions::VIEW_FINANCIAL_STATEMENTS)) {
        $moneyLinks[] = [
            'id' => 'finance',
            'label' => $labels['finance'],
            'href' => route('finance.index'),
            'icon' => 'chart',
            'active' => $isRoute(['finance.*']),
        ];
    }
    if ($allows(FinancialPermissions::VIEW_EXPENSE_MANAGEMENT)) {
        $moneyLinks[] = [
            'id' => 'operating-expenses',
            'label' => $labels['expenses'],
            'href' => route('operating-expenses.index'),
            'icon' => 'clipboard-list',
            'active' => $isRoute(['operating-expenses.*', 'expense-categories.*', 'vendors.*', 'recurring-expense-*']),
        ];
    }
    if ($allows(FinancialPermissions::VIEW_CAPITAL_MANAGEMENT)) {
        $moneyLinks[] = [
            'id' => 'capital-management',
            'label' => $labels['capital_management'],
            'href' => route('capital-management.index'),
            'icon' => 'landmark',
            'active' => $isRoute(['capital-management.*', 'funding-sources.*', 'capital-funding-transactions.*', 'asset-categories.*', 'fixed-assets.*']),
        ];
    }
    if ($moneyLinks !== []) {
        $managementGroups[] = [
            'id' => 'money',
            'label' => $labels['money'],
            'icon' => 'wallet',
            'links' => $moneyLinks,
            'active' => collect($moneyLinks)->contains('active', true),
        ];
    }

    $reportLinks = [];
    if ($allows(FinancialPermissions::VIEW_EXECUTIVE_DASHBOARD)) {
        $reportLinks[] = [
            'id' => 'executive',
            'label' => $labels['executive'],
            'href' => route('executive.index'),
            'icon' => 'chart',
            'active' => $isRoute(['executive.*']),
        ];
    }
    if ($allows(FinancialPermissions::VIEW_SAAS_METRICS)) {
        $reportLinks[] = [
            'id' => 'saas-metrics',
            'label' => $labels['saas'],
            'href' => route('saas-metrics.index'),
            'icon' => 'activity',
            'active' => $isRoute(['saas-metrics.*']),
        ];
    }
    if ($reportLinks !== []) {
        $managementGroups[] = [
            'id' => 'reports',
            'label' => $labels['reports_group'],
            'icon' => 'chart',
            'links' => $reportLinks,
            'active' => collect($reportLinks)->contains('active', true),
        ];
    }

    $operationsLinks = [];
    if ($canCreateClient) {
        $operationsLinks[] = [
            'id' => 'import',
            'label' => $labels['import'],
            'href' => route('clients.import'),
            'icon' => 'clipboard-list',
            'active' => $isRoute(['clients.import*']),
        ];
    }
    if ($admin) {
        $operationsLinks[] = [
            'id' => 'conflicts',
            'label' => $labels['conflicts'],
            'href' => route('conflicts.index'),
            'icon' => 'activity',
            'active' => $isRoute(['conflicts.*']),
        ];
    }
    if ($operationsLinks !== []) {
        $managementGroups[] = [
            'id' => 'operations-admin',
            'label' => $labels['operations_admin'],
            'icon' => 'wrench',
            'links' => $operationsLinks,
            'active' => collect($operationsLinks)->contains('active', true),
        ];
    }

    if ($allows(FinancialPermissions::MANAGE_FINANCIAL_SETTINGS)) {
        $systemLinks = [[
            'id' => 'settings',
            'label' => $labels['settings'],
            'href' => route('settings.index'),
            'icon' => 'settings',
            'active' => $isRoute(['settings.*']),
        ]];
        $managementGroups[] = [
            'id' => 'system',
            'label' => $labels['system'],
            'icon' => 'settings',
            'links' => $systemLinks,
            'active' => collect($systemLinks)->contains('active', true),
        ];
    }

    $advancedLinks = [];
    if ($allows(FinancialPermissions::VIEW_CASH_MANAGEMENT)) {
        $advancedLinks[] = [
            'id' => 'financial-accounts',
            'label' => $labels['financial_accounts'],
            'href' => route('financial-accounts.index'),
            'icon' => 'wallet',
            'active' => $isRoute(['financial-accounts.*', 'financial-transfers.*', 'cash-events.*']),
        ];
    }
    if ($allows(FinancialPermissions::VIEW_ACCOUNTING)) {
        $advancedLinks[] = [
            'id' => 'accounting',
            'label' => $labels['accounting'],
            'href' => route('accounting.index'),
            'icon' => 'landmark',
            'active' => $isRoute(['accounting.*']),
        ];
    }

    $activeManagementGroup = collect($managementGroups)->first(fn (array $group): bool => $group['active']);
    $activeManagementLink = collect($managementGroups)
        ->flatMap(fn (array $group) => $group['links'])
        ->first(fn (array $link): bool => $link['active']);
    $activeAdvancedLink = collect($advancedLinks)->first(fn (array $link): bool => $link['active']);
    $moreActive = $activeManagementLink !== null || $activeAdvancedLink !== null;

    $pageContextLabel = $labels['workspace'];
    $pageTitle = $labels['app'];
    if ($isToday) {
        $pageContextLabel = $labels['daily'];
        $pageTitle = $labels['today'];
    } elseif ($isWork) {
        $pageContextLabel = $labels['daily'];
        $pageTitle = $labels['work'];
    } elseif ($isClients) {
        $pageContextLabel = $labels['daily'];
        $pageTitle = $labels['clients'];
    } elseif ($activeManagementLink !== null) {
        $pageContextLabel = $activeManagementGroup['label'];
        $pageTitle = $activeManagementLink['label'];
    } elseif ($activeAdvancedLink !== null) {
        $pageContextLabel = $labels['advanced'];
        $pageTitle = $activeAdvancedLink['label'];
    } elseif (request()->routeIs('notifications.*')) {
        $pageContextLabel = $labels['workspace'];
        $pageTitle = $labels['notifications'];
    }

    $showAddClient = $canCreateClient && ($isToday || request()->routeIs('clients.index'));
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

                <div class="notify-sidebar__navigation">
                    <section class="notify-nav__section notify-nav__section--daily" aria-labelledby="notify-daily-navigation">
                        <p class="notify-nav__label" id="notify-daily-navigation">{{ $labels['daily'] }}</p>
                        <nav class="notify-nav" aria-label="{{ $labels['daily'] }}">
                            <x-notify.nav-item data-nav-destination="today" :href="route('dashboard', ['mode' => 'daily'])" :label="$labels['today']" icon="home" :active="$isToday" />
                            @if($canViewClients)
                                <x-notify.nav-item data-nav-destination="clients" :href="route('clients.index')" :label="$labels['clients']" icon="users" :active="$isClients" />
                            @endif
                            <x-notify.nav-item data-nav-destination="work" :href="route('dashboard', ['mode' => 'work'])" :label="$labels['work']" icon="briefcase" :active="$isWork" />
                        </nav>
                    </section>

                    @if($managementGroups !== [])
                        <section class="notify-nav__section notify-nav__section--management" aria-labelledby="notify-management-navigation">
                            <p class="notify-nav__label" id="notify-management-navigation">{{ $labels['management'] }}</p>
                            <div class="notify-nav-groups" aria-label="{{ $labels['management_navigation'] }}">
                                @foreach($managementGroups as $group)
                                    <x-notify.nav-group
                                        :group-id="$group['id']"
                                        :label="$group['label']"
                                        :icon="$group['icon']"
                                        :links="$group['links']"
                                        :active="$group['active']"
                                    />
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if($advancedLinks !== [])
                        <section class="notify-nav__section notify-nav__section--advanced" aria-labelledby="notify-advanced-navigation">
                            <p class="notify-nav__label" id="notify-advanced-navigation">{{ $labels['advanced'] }}</p>
                            <nav class="notify-nav notify-nav--advanced" aria-label="{{ $labels['advanced_navigation'] }}">
                                @foreach($advancedLinks as $link)
                                    <x-notify.nav-item
                                        data-nav-layer="advanced"
                                        data-nav-destination="{{ $link['id'] }}"
                                        :href="$link['href']"
                                        :label="$link['label']"
                                        :icon="$link['icon']"
                                        :active="$link['active']"
                                    />
                                @endforeach
                            </nav>
                        </section>
                    @endif
                </div>

                <div class="notify-sidebar__footer">
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
            :is-today="$isToday"
            :is-clients="$isClients"
            :is-work="$isWork"
            :management-groups="$managementGroups"
            :advanced-links="$advancedLinks"
            :more-active="$moreActive"
            :target-locale="$targetLocale"
            :target-locale-label="$targetLocaleLabel"
            :user-name="$user->name"
            :role-label="$roleLabel"
        />
    @endauth
</div>
</body>
</html>
