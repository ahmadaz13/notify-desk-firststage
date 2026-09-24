{{--
    Finance page header + section switcher (§12, §3.4). Horizontally scrollable on phone; the page never overflows.
    Expects: $active (section key), $title, optional $subtitle.
--}}
@php
    use App\Support\Features;
    use App\Support\Permissions;

    $user = auth()->user();
    $sections = collect([
        'overview' => ['route' => 'finance.index', 'permission' => Permissions::VIEW_FINANCIAL_STATEMENTS],
        'collections' => ['route' => 'finance.collections', 'permission' => Permissions::VIEW_FINANCIAL_REPORTS],
        'expenses' => ['route' => 'finance.expenses', 'permission' => Permissions::VIEW_EXPENSE_MANAGEMENT],
        'accounts' => ['route' => 'finance.accounts', 'permission' => Permissions::VIEW_CASH_MANAGEMENT],
        'accounting' => ['route' => 'finance.accounting', 'permission' => Permissions::VIEW_ACCOUNTING],
        'reports' => ['route' => 'finance.reports', 'permission' => Permissions::VIEW_FINANCIAL_STATEMENTS],
        'capital' => ['route' => 'finance.capital', 'permission' => Permissions::VIEW_CAPITAL_MANAGEMENT, 'enabled' => Features::capitalEnabled()],
    ])->filter(fn (array $section) => ($section['enabled'] ?? true) && Permissions::allows($user, $section['permission']));
@endphp
<header class="notify-fin-header">
    <div class="notify-fin-header__text">
        <p class="notify-fin-eyebrow">{{ __('notify.finance_hub.title') }}</p>
        <h1 class="notify-fin-title">{{ $title }}</h1>
        @isset($subtitle)
            <p class="notify-fin-subtitle">{{ $subtitle }}</p>
        @endisset
    </div>
</header>
<nav class="notify-fin-sections" aria-label="{{ __('notify.finance_hub.nav_label') }}" data-finance-sections>
    @foreach($sections as $key => $section)
        <a href="{{ route($section['route']) }}"
           class="notify-fin-sections__item @if($active === $key) is-active @endif"
           data-finance-section="{{ $key }}"
           @if($active === $key) aria-current="page" @endif>{{ __('notify.finance_hub.sections.'.$key) }}</a>
    @endforeach
</nav>
