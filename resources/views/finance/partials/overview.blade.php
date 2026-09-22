{{-- Management Boundary Note (Invariants) --}}
<div class="p4-callout-warning" style="margin-bottom: 20px;">
    <strong>{{ __('notify.finance.boundary_note_title') }}:</strong>
    <span> {{ __('notify.finance.boundary_note_text') }} هنا بل في لوحة SaaS Metrics.</span>
</div>

{{-- 1. Core Accounting Block (GL Source of Truth) --}}
<section class="p4-card" style="margin-bottom: 20px; border-inline-start: 4px solid #0055CC;">
    <div class="p4-card-head" style="margin-bottom: 14px;">
        <div>
            <span class="p4-badge p4-badge-primary" style="margin-bottom: 6px;">GL Accounting Authority</span>
            <h2 class="p4-card-title" style="font-size: 18px;">{{ __('notify.finance.accounting_core') }}</h2>
            <p class="p4-subtitle" style="margin: 2px 0 0 0;">أرقام محاسبية معتمدة مستخرجة مباشرة من دفتر الأستاذ العام وحركات النقد الفعلية.</p>
        </div>
        <a href="{{ route('finance.index', ['section' => 'reports'] + request()->query()) }}" class="p4-btn p4-btn-soft p4-btn-sm">
            عرض القوائم المفصلة
        </a>
    </div>

    <div class="p4-kpis">
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">النقد المتاح (Available Cash)</span>
            <span class="p4-kpi-value is-primary">{{ $money($reports['dashboard']['cash_available_minor']) }}</span>
            <span class="p4-kpi-meta">D1 cash + GL reconciliation</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">ذمم العملاء (Accounts Receivable)</span>
            <span class="p4-kpi-value">{{ $money($reports['dashboard']['accounts_receivable_minor']) }}</span>
            <span class="p4-kpi-meta">فواتير مستحقة غير مسددة</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">الذمم المتأخرة (Overdue)</span>
            <span class="p4-kpi-value is-danger">{{ $money($reports['dashboard']['overdue_receivables_minor']) }}</span>
            <span class="p4-kpi-meta">تجاوزت تاريخ الاستحقاق</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">الإيراد المعترف به (Recognized Revenue)</span>
            <span class="p4-kpi-value is-success">{{ $money($reports['dashboard']['recognized_revenue_this_period_minor']) }}</span>
            <span class="p4-kpi-meta">عن هذه الفترة (P&amp;L)</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">المصاريف التشغيلية (Operating Expenses)</span>
            <span class="p4-kpi-value is-warning">{{ $money($reports['dashboard']['operating_expenses_this_period_minor']) }}</span>
            <span class="p4-kpi-meta">مصاريف تشغيلية فعلية</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">صافي الدخل الإداري (Net Income)</span>
            <span class="p4-kpi-value {{ $reports['dashboard']['management_net_income_this_period_minor'] >= 0 ? 'is-success' : 'is-danger' }}">
                {{ $money($reports['dashboard']['management_net_income_this_period_minor']) }}
            </span>
            <span class="p4-kpi-meta">إيراد ناقص مصاريف</span>
        </div>
    </div>
</section>

{{-- 2. Commercial SaaS Metrics Block (Decoupled from GL) --}}
<section class="p4-card" style="margin-bottom: 20px; border-inline-start: 4px solid #10B981; background: #F8FAFC;">
    <div class="p4-card-head" style="margin-bottom: 14px;">
        <div>
            <span class="p4-badge p4-badge-success" style="margin-bottom: 6px;">Commercial Contracts Engine</span>
            <h2 class="p4-card-title" style="font-size: 18px; color: #065F46;">{{ __('notify.finance.saas_block_title') }}</h2>
            <p class="p4-subtitle" style="margin: 2px 0 0 0;">{{ __('notify.finance.saas_block_subtitle') }}</p>
        </div>
        <a href="{{ route('saas-metrics.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">
            لوحة SaaS Metrics
        </a>
    </div>

    <div class="p4-kpis">
        <div class="p4-kpi-card" style="background:#FFFFFF;">
            <span class="p4-kpi-label">{{ __('notify.finance.mrr') }}</span>
            <span class="p4-kpi-value is-primary">{{ $money($saasReport['ending_mrr_minor']) }}</span>
            <span class="p4-kpi-meta">قيمة الاشتراكات الشهرية النشطة</span>
        </div>
        <div class="p4-kpi-card" style="background:#FFFFFF;">
            <span class="p4-kpi-label">{{ __('notify.finance.arr') }}</span>
            <span class="p4-kpi-value is-success">{{ $money($saasReport['ending_arr_minor']) }}</span>
            <span class="p4-kpi-meta">المعدل السنوي المعياري (MRR × 12)</span>
        </div>
        <div class="p4-kpi-card" style="background:#FFFFFF;">
            <span class="p4-kpi-label">{{ __('notify.finance.active_subscriptions') }}</span>
            <span class="p4-kpi-value">{{ $saasReport['active_subscriptions'] }}</span>
            <span class="p4-kpi-meta">اشتراك نشط في الخدمة</span>
        </div>
        <div class="p4-kpi-card" style="background:#FFFFFF;">
            <span class="p4-kpi-label">{{ __('notify.finance.upcoming_renewals') }} (30 يوم)</span>
            <span class="p4-kpi-value is-warning">{{ $saasReport['renewal_metrics']['due_30_count'] ?? 0 }}</span>
            <span class="p4-kpi-meta">منها {{ $saasReport['renewal_metrics']['due_7_count'] ?? 0 }} مستحقة خلال 7 أيام</span>
        </div>
        <div class="p4-kpi-card" style="background:#FFFFFF;">
            <span class="p4-kpi-label">{{ __('notify.finance.net_new_mrr') }}</span>
            <span class="p4-kpi-value {{ ($saasReport['net_new_mrr_minor'] ?? 0) >= 0 ? 'is-success' : 'is-danger' }}">
                {{ $money($saasReport['net_new_mrr_minor'] ?? 0) }}
            </span>
            <span class="p4-kpi-meta">صافي حركة النمو لهذه الفترة</span>
        </div>
    </div>
</section>

{{-- 3. Core Financial Statements Summary --}}
<x-notify.collapsible-section
    :title="__('notify.finance.financial_statements')"
    subtitle="ملخص الأرباح والخسائر والميزانية والتدفقات النقدية"
    icon="book-open"
    :open="true"
>
    <div class="p4-grid-2">
        {{-- P&L --}}
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

        {{-- Balance Sheet --}}
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

    {{-- Cash Flow & Reconciliation Row --}}
    <div class="p4-grid-2" style="margin-top: 14px;">
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
                        <tr><th>النقد الختامي</th><td><strong>{{ $money($reports['cash_flow']['closing_cash_minor']) }}</strong></td></tr>
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
