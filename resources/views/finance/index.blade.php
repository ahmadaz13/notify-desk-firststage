@extends('layouts.app')

@php
    $money = fn (int $minor) => ($minor < 0 ? '-' : '') . \App\Support\Money::fromMinorUnits(abs($minor))->format() . ' ' . __('notify.common.currency_jod');
@endphp

@section('content')
<div class="p4-wrap">
    {{-- Page Header --}}
    <header class="p4-header">
        <div class="p4-header-main">
            <div class="p4-eyebrow">{{ __('notify.navigation.finance') }} / {{ __('notify.finance.title') }}</div>
            <h1 class="p4-title">{{ __('notify.finance.title') }}</h1>
            <p class="p4-subtitle">{{ __('notify.finance.subtitle') }}</p>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                <a href="{{ route('financial-accounts.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">إدارة النقد والحسابات</a>
                <a href="{{ route('operating-expenses.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">المصاريف التشغيلية</a>
                <a href="{{ route('capital-management.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">التمويل والأصول</a>
                <a href="{{ route('accounting.index') }}" class="p4-btn p4-btn-ghost p4-btn-sm">المحاسبة العامة</a>
            </div>
        </div>
        <div class="p4-header-actions">
            <form method="GET" action="{{ route('finance.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                <div class="p4-field">
                    <label>الفترة</label>
                    <select name="range" class="p4-select" style="min-width:130px">
                        @foreach(['today' => 'اليوم', 'this_month' => 'هذا الشهر', 'previous_month' => 'الشهر السابق', 'this_year' => 'هذا العام', 'previous_year' => 'العام السابق', 'custom_date_range' => 'مخصص'] as $value => $label)
                            <option value="{{ $value }}" @selected($period->range === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p4-field">
                    <label>من</label>
                    <input type="date" name="date_from" class="p4-input" value="{{ request('date_from', $period->start->toDateString()) }}">
                </div>
                <div class="p4-field">
                    <label>إلى</label>
                    <input type="date" name="date_to" class="p4-input" value="{{ request('date_to', $period->end->toDateString()) }}">
                </div>
                <div class="p4-field">
                    <label>المقارنة</label>
                    <select name="comparison" class="p4-select" style="min-width:130px">
                        @foreach(['none' => 'بدون مقارنة', 'previous_period' => 'الفترة السابقة', 'previous_year_same_period' => 'العام السابق'] as $value => $label)
                            <option value="{{ $value }}" @selected($period->comparisonMode === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="p4-btn p4-btn-primary" type="submit">تطبيق</button>
            </form>
        </div>
    </header>

    {{-- Management Boundary Note --}}
    <div class="p4-callout-warning">
        <strong>{{ __('notify.finance.boundary_note_title') }}:</strong>
        <span> {{ __('notify.finance.boundary_note_text') }} هنا بل في لوحة SaaS Metrics.</span>
    </div>

    {{-- Dashboard KPIs --}}
    <div class="p4-kpis">
        @foreach([
            'Cash Available' => ['label' => 'النقد المتاح', 'amount' => $reports['dashboard']['cash_available_minor'], 'meta' => 'D1 cash + GL reconciliation', 'class' => 'is-primary'],
            'Accounts Receivable' => ['label' => 'الذمم المدينة', 'amount' => $reports['dashboard']['accounts_receivable_minor'], 'meta' => 'F1 reporting service', 'class' => ''],
            'Overdue Receivables' => ['label' => 'الذمم المتأخرة', 'amount' => $reports['dashboard']['overdue_receivables_minor'], 'meta' => 'متأخرة عن الاستحقاق', 'class' => 'is-danger'],
            'Deferred Revenue' => ['label' => 'الإيراد المؤجل', 'amount' => $reports['dashboard']['deferred_revenue_minor'], 'meta' => 'التزامات أداء مستقبلية', 'class' => ''],
            'Recognized Revenue' => ['label' => 'الإيراد المعترف به', 'amount' => $reports['dashboard']['recognized_revenue_this_period_minor'], 'meta' => 'عن هذه الفترة', 'class' => 'is-success'],
            'Operating Expenses' => ['label' => 'المصاريف التشغيلية', 'amount' => $reports['dashboard']['operating_expenses_this_period_minor'], 'meta' => 'عن هذه الفترة', 'class' => 'is-warning'],
            'Management Net Income' => ['label' => 'صافي الدخل الإداري', 'amount' => $reports['dashboard']['management_net_income_this_period_minor'], 'meta' => 'إيراد ناقص مصاريف', 'class' => $reports['dashboard']['management_net_income_this_period_minor'] >= 0 ? 'is-success' : 'is-danger'],
            'Sales Tax Payable' => ['label' => 'ضريبة المبيعات المستحقة', 'amount' => $reports['dashboard']['sales_tax_payable_minor'], 'meta' => 'مستحقة للدفع', 'class' => ''],
        ] as $key => $item)
            <div class="p4-kpi-card">
                <span class="p4-kpi-label">{{ $item['label'] }}</span>
                <span class="p4-kpi-value {{ $item['class'] }}">{{ $money($item['amount']) }}</span>
                <span class="p4-kpi-meta">{{ $item['meta'] }}</span>
            </div>
        @endforeach
    </div>

    {{-- Profit & Loss & Balance Sheet --}}
    <x-notify.collapsible-section
        :title="__('notify.finance.financial_statements')"
        subtitle="الأرباح والخسائر والميزانية العمومية"
        icon="book-open"
        :open="true"
    >
        <div class="p4-grid-2">
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.finance.profit_loss') }}</h2>
                    <a class="p4-btn p4-btn-ghost p4-btn-sm" href="{{ route('finance.export', ['report' => 'profit-and-loss'] + request()->query()) }}">تصدير CSV</a>
                </div>
                <div class="p4-table-wrap">
                    <table class="p4-table">
                        <tbody>
                            <tr><th>{{ __('notify.finance.saas_revenue') }}</th><td>{{ $money($reports['profit_and_loss']['saas_revenue_minor']) }}</td></tr>
                            <tr><th>{{ __('notify.finance.onetime_revenue') }}</th><td>{{ $money($reports['profit_and_loss']['one_time_revenue_minor']) }}</td></tr>
                            <tr><th>إجمالي الإيراد المعترف به</th><td><strong>{{ $money($reports['profit_and_loss']['total_revenue_minor']) }}</strong></td></tr>
                            <tr><th>إجمالي المصاريف التشغيلية</th><td>{{ $money($reports['profit_and_loss']['total_expenses_minor']) }}</td></tr>
                            <tr><th>صافي الدخل الإداري</th><td><strong>{{ $money($reports['profit_and_loss']['net_income_minor']) }}</strong></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.finance.balance_sheet') }}</h2>
                    <a class="p4-btn p4-btn-ghost p4-btn-sm" href="{{ route('finance.export', ['report' => 'balance-sheet'] + request()->query()) }}">تصدير CSV</a>
                </div>
                <div class="p4-table-wrap">
                    <table class="p4-table">
                        <tbody>
                            <tr><th>{{ __('notify.finance.total_assets') }}</th><td>{{ $money($reports['balance_sheet']['total_assets_minor']) }}</td></tr>
                            <tr><th>{{ __('notify.finance.total_liabilities') }}</th><td>{{ $money($reports['balance_sheet']['total_liabilities_minor']) }}</td></tr>
                            <tr><th>{{ __('notify.finance.equity') }}</th><td>{{ $money($reports['balance_sheet']['total_equity_minor']) }}</td></tr>
                            <tr>
                                <th>فارق معادلة الميزانية</th>
                                <td>
                                    <span class="p4-badge {{ $reports['balance_sheet']['equation_difference_minor'] === 0 ? 'p4-badge-success' : 'p4-badge-danger' }}">
                                        {{ $money($reports['balance_sheet']['equation_difference_minor']) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <span class="p4-kpi-meta" style="margin-top:8px">ملاحظة: الأصول الثابتة تظهر بتكلفة الاقتناء التاريخية وليست بالقيمة الدفترية بعد الإهلاك.</span>
            </div>
        </div>
    </x-notify.collapsible-section>

    {{-- Cash Flow & Reconciliation --}}
    <x-notify.collapsible-section
        :title="__('notify.finance.cash_flow_reconciliation')"
        subtitle="حركة النقد وفحوصات المطابقة التشغيلية"
        :badge="$reports['reconciliation']['ok'] ? __('notify.statuses.reconciled') : __('notify.statuses.needs_review')"
        :badgeVariant="$reports['reconciliation']['ok'] ? 'success' : 'danger'"
        icon="dollar-sign"
        :open="false"
    >
        <div class="p4-grid-2">
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.finance.cash_flow') }}</h2>
                    <a class="p4-btn p4-btn-ghost p4-btn-sm" href="{{ route('finance.export', ['report' => 'cash-flow'] + request()->query()) }}">تصدير CSV</a>
                </div>
                <div class="p4-table-wrap">
                    <table class="p4-table">
                        <tbody>
                            <tr><th>النقد الافتتاحي</th><td>{{ $money($reports['cash_flow']['opening_cash_minor']) }}</td></tr>
                            <tr><th>التدفقات التشغيلية</th><td>{{ $money($reports['cash_flow']['operating_cash_flow_minor']) }}</td></tr>
                            <tr><th>التدفقات الاستثمارية</th><td>{{ $money($reports['cash_flow']['investing_cash_flow_minor']) }}</td></tr>
                            <tr><th>التدفقات التمويلية</th><td>{{ $money($reports['cash_flow']['financing_cash_flow_minor']) }}</td></tr>
                            <tr><th>التحويلات الداخلية</th><td>{{ $money($reports['cash_flow']['internal_transfer_minor']) }}</td></tr>
                            <tr><th>النقد الختامي</th><td><strong>{{ $money($reports['cash_flow']['closing_cash_minor']) }}</strong></td></tr>
                            <tr>
                                <th>فارق الإغلاق</th>
                                <td>
                                    <span class="p4-badge {{ $reports['cash_flow']['closing_difference_minor'] === 0 ? 'p4-badge-success' : 'p4-badge-danger' }}">
                                        {{ $money($reports['cash_flow']['closing_difference_minor']) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.finance.reconciliation') }}</h2>
                    <span class="p4-badge {{ $reports['reconciliation']['ok'] ? 'p4-badge-success' : 'p4-badge-danger' }}">
                        {{ $reports['reconciliation']['ok'] ? __('notify.statuses.reconciled') : __('notify.statuses.needs_review') }}
                    </span>
                </div>
                <div class="p4-table-wrap">
                    <table class="p4-table">
                        <tbody>
                            @foreach($reports['reconciliation']['checks'] as $check => $difference)
                                <tr>
                                    <th>{{ str_replace('_', ' ', $check) }}</th>
                                    <td>
                                        <span class="p4-badge {{ $difference === 0 ? 'p4-badge-success' : 'p4-badge-danger' }}">
                                            {{ $money($difference) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-notify.collapsible-section>

    {{-- AR Aging & Deferred Revenue --}}
    <x-notify.collapsible-section
        :title="__('notify.finance.ar_aging_deferred')"
        subtitle="جدولة الذمم والتزامات الأداء المستقبلية"
        icon="clock"
        :open="false"
    >
        <div class="p4-grid-2">
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.collections.aging') }}</h2>
                    <a class="p4-btn p4-btn-ghost p4-btn-sm" href="{{ route('finance.export', ['report' => 'ar-aging'] + request()->query()) }}">تصدير CSV</a>
                </div>
                <div class="p4-table-wrap">
                    <table class="p4-table">
                        <tbody>
                            @foreach($reports['ar_aging']['summary']['buckets'] as $bucket => $amount)
                                <tr><th>{{ str_replace('_', ' ', $bucket) }}</th><td>{{ $money($amount) }}</td></tr>
                            @endforeach
                            <tr><th>إجمالي الذمم</th><td><strong>{{ $money($reports['ar_aging']['summary']['total_ar_minor']) }}</strong></td></tr>
                            <tr><th>فارق دفتر الأستاذ</th><td>{{ $money($reports['ar_aging']['summary']['difference_minor']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.finance.deferred_revenue') }}</h2>
                    <a class="p4-btn p4-btn-ghost p4-btn-sm" href="{{ route('finance.export', ['report' => 'deferred-revenue'] + request()->query()) }}">تصدير CSV</a>
                </div>
                <div class="p4-table-wrap">
                    <table class="p4-table">
                        <tbody>
                            @foreach($reports['deferred_revenue']['summary'] as $label => $amount)
                                <tr><th>{{ str_replace('_', ' ', $label) }}</th><td>{{ $money((int) $amount) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-notify.collapsible-section>

    {{-- Customer Credits, Tax & Expenses --}}
    <x-notify.collapsible-section
        title="أرصدة العملاء والضرائب والمصاريف (Credits, Tax & Expenses)"
        subtitle="الأرصدة غير المخصصة ووعاء ضريبة المبيعات وتوزيع المصاريف"
        icon="pie-chart"
        :open="false"
    >
        <div class="p4-grid-2">
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">أرصدة العملاء والضريبة (Credits &amp; Tax)</h2>
                </div>
                <div class="p4-table-wrap">
                    <table class="p4-table">
                        <tbody>
                            <tr><th>{{ __('notify.finance.unallocated_payments') }}</th><td>{{ $money($reports['customer_credits']['unallocated_payment_credit_minor']) }}</td></tr>
                            <tr><th>{{ __('notify.finance.credit_note_balances') }}</th><td>{{ $money($reports['customer_credits']['credit_note_credit_minor']) }}</td></tr>
                            <tr><th>ضريبة الفواتير</th><td>{{ $money($reports['sales_tax']['invoice_tax_minor']) }}</td></tr>
                            <tr><th>تخفيضات ضريبة الإشعارات الدائنة</th><td>{{ $money($reports['sales_tax']['credit_note_tax_reductions_minor']) }}</td></tr>
                            <tr><th>صافي ضريبة المبيعات المستحقة</th><td><strong>{{ $money($reports['sales_tax']['net_sales_tax_payable_minor']) }}</strong></td></tr>
                        </tbody>
                    </table>
                </div>
                <span class="p4-kpi-meta" style="margin-top:8px">{{ $reports['sales_tax']['warning'] }}</span>
            </div>

            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">المصاريف والأصول (Expenses &amp; Capital)</h2>
                    <a class="p4-btn p4-btn-ghost p4-btn-sm" href="{{ route('finance.export', ['report' => 'expense-breakdown'] + request()->query()) }}">تصدير CSV</a>
                </div>
                <div class="p4-table-wrap">
                    <table class="p4-table">
                        <tbody>
                            <tr><th>مصاريف ممولة من الشركة</th><td>{{ $money($reports['expenses']['company_funded_minor']) }}</td></tr>
                            <tr><th>مصاريف ممولة شخصياً</th><td>{{ $money($reports['expenses']['personally_funded_minor']) }}</td></tr>
                            <tr><th>أصول ممولة من الشركة</th><td>{{ $money($reports['capital_assets']['company_funded_assets_minor']) }}</td></tr>
                            <tr><th>أصول ممولة شخصياً</th><td>{{ $money($reports['capital_assets']['personally_funded_assets_minor']) }}</td></tr>
                            <tr><th>عدد الأصول النشطة</th><td><strong>{{ $reports['capital_assets']['active_asset_count'] }}</strong></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-notify.collapsible-section>
</div>
@endsection
