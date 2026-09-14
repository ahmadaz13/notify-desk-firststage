@extends('layouts.app')

@php
    $money = fn (int $minor) => ($minor < 0 ? '-' : '') . \App\Support\Money::fromMinorUnits(abs($minor))->format() . ' د.أ';
@endphp

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">Finance / Management Reporting</div>
        <h1 class="page-title">تقارير الإدارة المالية</h1>
        <div class="muted" style="margin-top:5px">تقارير إدارية داخلية مبنية على دفتر الأستاذ والمصادر التشغيلية المعتمدة. ليست قوائم مالية نظامية مدققة.</div>
    </div>
    <form method="GET" action="{{ route('finance.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <select name="range">
            @foreach(['today' => 'Today', 'this_month' => 'This month', 'previous_month' => 'Previous month', 'this_year' => 'This year', 'previous_year' => 'Previous year', 'custom_date_range' => 'Custom'] as $value => $label)
                <option value="{{ $value }}" @selected($period->range === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from', $period->start->toDateString()) }}">
        <input type="date" name="date_to" value="{{ request('date_to', $period->end->toDateString()) }}">
        <select name="comparison">
            @foreach(['none' => 'No comparison', 'previous_period' => 'Previous period', 'previous_year_same_period' => 'Previous year'] as $value => $label)
                <option value="{{ $value }}" @selected($period->comparisonMode === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary" type="submit">Apply</button>
    </form>
</div>

<div class="card" style="margin-bottom:16px;border-color:var(--nd-warning)">
    <strong>Management reporting boundary</strong>
    <div class="muted" style="margin-top:6px">الإيراد هنا هو الإيراد المحاسبي المعترف به وليس التحصيلات. التمويل الرأسمالي ليس إيراداً، والأصول الثابتة تظهر بتكلفة الاقتناء المسجلة لأن الإهلاك غير مطبق بعد. لا توجد MRR/ARR أو مقاييس SaaS تنفيذية في F1.</div>
</div>

<div class="grid grid-4" style="margin-bottom:16px">
    @foreach([
        'Cash Available' => $reports['dashboard']['cash_available_minor'],
        'Accounts Receivable' => $reports['dashboard']['accounts_receivable_minor'],
        'Overdue Receivables' => $reports['dashboard']['overdue_receivables_minor'],
        'Deferred Revenue' => $reports['dashboard']['deferred_revenue_minor'],
        'Recognized Revenue' => $reports['dashboard']['recognized_revenue_this_period_minor'],
        'Operating Expenses' => $reports['dashboard']['operating_expenses_this_period_minor'],
        'Management Net Income' => $reports['dashboard']['management_net_income_this_period_minor'],
        'Sales Tax Payable' => $reports['dashboard']['sales_tax_payable_minor'],
    ] as $label => $amount)
        <div class="card">
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value" style="font-size:18px">{{ $money($amount) }}</div>
            <small class="muted">Source: {{ in_array($label, ['Cash Available'], true) ? 'D1 cash + GL reconciliation' : 'F1 reporting service' }}</small>
        </div>
    @endforeach
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Profit &amp; Loss</h2>
            <a class="btn btn-ghost" href="{{ route('finance.export', ['report' => 'profit-and-loss'] + request()->query()) }}">CSV</a>
        </div>
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><th>SaaS Subscription Revenue</th><td>{{ $money($reports['profit_and_loss']['saas_revenue_minor']) }}</td></tr>
                    <tr><th>One-Time Service Revenue</th><td>{{ $money($reports['profit_and_loss']['one_time_revenue_minor']) }}</td></tr>
                    <tr><th>Total Recognized Revenue</th><td>{{ $money($reports['profit_and_loss']['total_revenue_minor']) }}</td></tr>
                    <tr><th>Total Operating Expenses</th><td>{{ $money($reports['profit_and_loss']['total_expenses_minor']) }}</td></tr>
                    <tr><th>Management Net Income</th><td>{{ $money($reports['profit_and_loss']['net_income_minor']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Balance Sheet</h2>
            <a class="btn btn-ghost" href="{{ route('finance.export', ['report' => 'balance-sheet'] + request()->query()) }}">CSV</a>
        </div>
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><th>Total Assets</th><td>{{ $money($reports['balance_sheet']['total_assets_minor']) }}</td></tr>
                    <tr><th>Total Liabilities</th><td>{{ $money($reports['balance_sheet']['total_liabilities_minor']) }}</td></tr>
                    <tr><th>Total Equity</th><td>{{ $money($reports['balance_sheet']['total_equity_minor']) }}</td></tr>
                    <tr><th>Equation Difference</th><td><span class="badge {{ $reports['balance_sheet']['equation_difference_minor'] === 0 ? 'green' : 'red' }}">{{ $money($reports['balance_sheet']['equation_difference_minor']) }}</span></td></tr>
                </tbody>
            </table>
        </div>
        <small class="muted">Fixed assets are acquisition cost, not depreciated book value.</small>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Cash Flow</h2>
            <a class="btn btn-ghost" href="{{ route('finance.export', ['report' => 'cash-flow'] + request()->query()) }}">CSV</a>
        </div>
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><th>Opening Cash</th><td>{{ $money($reports['cash_flow']['opening_cash_minor']) }}</td></tr>
                    <tr><th>Operating</th><td>{{ $money($reports['cash_flow']['operating_cash_flow_minor']) }}</td></tr>
                    <tr><th>Investing</th><td>{{ $money($reports['cash_flow']['investing_cash_flow_minor']) }}</td></tr>
                    <tr><th>Financing</th><td>{{ $money($reports['cash_flow']['financing_cash_flow_minor']) }}</td></tr>
                    <tr><th>Internal Transfers</th><td>{{ $money($reports['cash_flow']['internal_transfer_minor']) }}</td></tr>
                    <tr><th>Closing Cash</th><td>{{ $money($reports['cash_flow']['closing_cash_minor']) }}</td></tr>
                    <tr><th>Closing Difference</th><td><span class="badge {{ $reports['cash_flow']['closing_difference_minor'] === 0 ? 'green' : 'red' }}">{{ $money($reports['cash_flow']['closing_difference_minor']) }}</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Reporting Reconciliation</h2>
            <span class="badge {{ $reports['reconciliation']['ok'] ? 'green' : 'red' }}">{{ $reports['reconciliation']['ok'] ? 'OK' : 'Review' }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <tbody>
                    @foreach($reports['reconciliation']['checks'] as $check => $difference)
                        <tr>
                            <th>{{ str_replace('_', ' ', $check) }}</th>
                            <td>{{ $money($difference) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>AR Aging</h2>
            <a class="btn btn-ghost" href="{{ route('finance.export', ['report' => 'ar-aging'] + request()->query()) }}">CSV</a>
        </div>
        <div class="table-wrap">
            <table>
                <tbody>
                    @foreach($reports['ar_aging']['summary']['buckets'] as $bucket => $amount)
                        <tr><th>{{ str_replace('_', ' ', $bucket) }}</th><td>{{ $money($amount) }}</td></tr>
                    @endforeach
                    <tr><th>Total AR</th><td>{{ $money($reports['ar_aging']['summary']['total_ar_minor']) }}</td></tr>
                    <tr><th>GL Difference</th><td>{{ $money($reports['ar_aging']['summary']['difference_minor']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Deferred Revenue</h2>
            <a class="btn btn-ghost" href="{{ route('finance.export', ['report' => 'deferred-revenue'] + request()->query()) }}">CSV</a>
        </div>
        <div class="table-wrap">
            <table>
                <tbody>
                    @foreach($reports['deferred_revenue']['summary'] as $label => $amount)
                        <tr><th>{{ str_replace('_', ' ', $label) }}</th><td>{{ $money((int) $amount) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Customer Credits &amp; Tax</h2>
        </div>
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><th>Unallocated Payment Credit</th><td>{{ $money($reports['customer_credits']['unallocated_payment_credit_minor']) }}</td></tr>
                    <tr><th>Credit-Note Credit</th><td>{{ $money($reports['customer_credits']['credit_note_credit_minor']) }}</td></tr>
                    <tr><th>Invoice Tax</th><td>{{ $money($reports['sales_tax']['invoice_tax_minor']) }}</td></tr>
                    <tr><th>Credit-Note Tax Reductions</th><td>{{ $money($reports['sales_tax']['credit_note_tax_reductions_minor']) }}</td></tr>
                    <tr><th>Net Sales Tax Payable</th><td>{{ $money($reports['sales_tax']['net_sales_tax_payable_minor']) }}</td></tr>
                </tbody>
            </table>
        </div>
        <small class="muted">{{ $reports['sales_tax']['warning'] }}</small>
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Expenses &amp; Capital</h2>
            <a class="btn btn-ghost" href="{{ route('finance.export', ['report' => 'expense-breakdown'] + request()->query()) }}">CSV</a>
        </div>
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><th>Company-Funded Expenses</th><td>{{ $money($reports['expenses']['company_funded_minor']) }}</td></tr>
                    <tr><th>Personally-Funded Expenses</th><td>{{ $money($reports['expenses']['personally_funded_minor']) }}</td></tr>
                    <tr><th>Company-Funded Assets</th><td>{{ $money($reports['capital_assets']['company_funded_assets_minor']) }}</td></tr>
                    <tr><th>Personally-Funded Assets</th><td>{{ $money($reports['capital_assets']['personally_funded_assets_minor']) }}</td></tr>
                    <tr><th>Active Asset Count</th><td>{{ $reports['capital_assets']['active_asset_count'] }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
