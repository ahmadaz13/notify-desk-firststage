@extends('layouts.app')

@section('content')
<div class="p5-wrap">
    {{-- Header --}}
    <header class="p5-header">
        <div class="p5-header-main">
            <div class="p5-eyebrow">{{ __('notify.navigation.finance') }} / {{ __('notify.accounting.title') }}</div>
            <h1 class="p5-title">{{ __('notify.accounting.title') }}</h1>
            <p class="p5-subtitle">قيود مزدوجة غير قابلة للتعديل، دليل الحسابات، ميزان المراجعة، الفترات المحاسبية، ومطابقة جداول الاعتراف بالإيراد.</p>
        </div>
        <div class="p5-header-actions">
            @can(\App\Support\Permissions::RUN_ACCOUNTING_BACKFILL)
                <form method="POST" action="{{ route('accounting.backfill') }}">
                    @csrf
                    <input type="hidden" name="dry_run" value="1">
                    <button class="p5-btn p5-btn-soft p5-btn-sm" type="submit">{{ __('notify.accounting.dry_run_backfill') }}</button>
                </form>
            @endcan
            @can(\App\Support\Permissions::RUN_REVENUE_RECOGNITION)
                <form method="POST" action="{{ route('accounting.revenue-schedules.backfill') }}">
                    @csrf
                    <input type="hidden" name="dry_run" value="1">
                    <button class="p5-btn p5-btn-soft p5-btn-sm" type="submit">{{ __('notify.accounting.dry_run_schedules') }}</button>
                </form>
            @endcan
        </div>
    </header>

    {{-- Policy Notice --}}
    <div class="p5-list-item" style="border-inline-start:4px solid #D97706;background:#FFFBEB">
        <strong style="font-size:13px;color:#92400E">{{ __('notify.accounting.policy_notice_title') }}</strong>
        <span class="p5-kpi-meta" style="color:#B45309">
            {{ __('notify.accounting.policy_notice_text') }}
        </span>
    </div>

    {{-- KPI Row 1: Trial Balance & Reconciliation --}}
    <div class="p5-kpis">
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.accounting.trial_balance_debit') }}</span>
            <span class="p5-kpi-value is-primary">{{ \App\Support\Money::fromMinorUnits($trialBalance['total_debit_minor'])->format() }} د.أ</span>
            <span class="p5-kpi-meta">إجمالي حركات المدين</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.accounting.trial_balance_credit') }}</span>
            <span class="p5-kpi-value is-primary">{{ \App\Support\Money::fromMinorUnits($trialBalance['total_credit_minor'])->format() }} د.أ</span>
            <span class="p5-kpi-meta">إجمالي حركات الدائن</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.accounting.balance_difference') }}</span>
            <span class="p5-kpi-value {{ $trialBalance['difference_minor'] === 0 ? 'is-success' : 'is-danger' }}">{{ \App\Support\Money::fromMinorUnits(abs($trialBalance['difference_minor']))->format() }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ $trialBalance['difference_minor'] === 0 ? 'توازن تام' : 'يوجد فارق غير متوازن' }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.accounting.reconciliation') }}</span>
            <span class="p5-kpi-value {{ $reconciliationResult['ok'] ? 'is-success' : 'is-danger' }}">{{ $reconciliationResult['ok'] ? __('notify.statuses.reconciled') : __('notify.statuses.needs_review') }}</span>
            <span class="p5-kpi-meta">تكامل النقد مع دفتر الأستاذ</span>
        </div>
    </div>

    {{-- KPI Row 2: Revenue Recognition Dashboard --}}
    <div class="p5-kpis">
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.accounting.revenue_schedules') }}</span>
            <span class="p5-kpi-value">{{ $revenueRecognitionDashboard['schedule_count'] }}</span>
            <span class="p5-kpi-meta">إجمالي الجداول المنشأة</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">فترات مستحقة للاعتراف</span>
            <span class="p5-kpi-value is-primary">{{ $revenueRecognitionDashboard['due_period_count'] }}</span>
            <span class="p5-kpi-meta">جاهزة للترحيل الدوري</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">قائمة المراجعة اليدوية</span>
            <span class="p5-kpi-value {{ $revenueRecognitionDashboard['needs_review_count'] === 0 ? 'is-success' : 'is-warning' }}">{{ $revenueRecognitionDashboard['needs_review_count'] }}</span>
            <span class="p5-kpi-meta">تتطلب تأكيد إداري</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">الإيراد المؤجل المتبقي</span>
            <span class="p5-kpi-value">{{ \App\Support\Money::fromMinorUnits($revenueRecognitionDashboard['deferred_remaining_minor'])->format() }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">التزامات أداء مستقبلية</span>
        </div>
    </div>

    {{-- Revenue Recognition Diagnostics & Review Queue --}}
    <x-notify.collapsible-section
        title="تشخيصات وطابور مراجعة الاعتراف بالإيراد"
        subtitle="إيراد مرحل هذا الشهر وطابور الجداول التي تتطلب تأكيداً إدارياً"
        :badge="$revenueRecognitionDashboard['review_queue']->count() > 0 ? $revenueRecognitionDashboard['review_queue']->count().' مراجعة' : 'مكتمل'"
        :badgeVariant="$revenueRecognitionDashboard['review_queue']->count() > 0 ? 'warning' : 'success'"
        icon="activity"
        :open="true"
    >
        <div class="p5-grid-2">
            {{-- Diagnostics & Dry-run --}}
            <div class="p5-card">
                <div class="p5-card-head">
                    <h2 class="p5-card-title">{{ __('notify.accounting.mtd_diagnostics') }}</h2>
                    <span class="p5-kpi-meta">إيراد محاسبي مرحل هذا الشهر</span>
                </div>
                <div class="p5-list">
                    <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <strong>إيراد اشتراكات SaaS المعترف به</strong>
                            <span class="p5-kpi-meta">عن الشهر الحالي حتى تاريخه</span>
                        </div>
                        <strong style="font-size:15px;color:#0055CC">{{ \App\Support\Money::fromMinorUnits($revenueRecognitionDashboard['recognized_saas_mtd_minor'])->format() }} {{ __('notify.common.currency_jod') }}</strong>
                    </div>
                    <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <strong>إيراد الخدمات الفردية المعترف به</strong>
                            <span class="p5-kpi-meta">خدمات منجزة هذا الشهر</span>
                        </div>
                        <strong style="font-size:15px;color:#0055CC">{{ \App\Support\Money::fromMinorUnits($revenueRecognitionDashboard['recognized_one_time_mtd_minor'])->format() }} {{ __('notify.common.currency_jod') }}</strong>
                    </div>
                </div>
                @can(\App\Support\Permissions::RUN_REVENUE_RECOGNITION)
                    <form method="POST" action="{{ route('accounting.revenue-recognition.run') }}" style="display:flex;gap:8px;align-items:flex-end;margin-top:14px;flex-wrap:wrap">
                        @csrf
                        <div class="p5-field">
                            <label>حتى تاريخ</label>
                            <input name="through" type="date" value="{{ now()->toDateString() }}" class="p5-input" style="padding:5px 8px;font-size:12px">
                        </div>
                        <input type="hidden" name="dry_run" value="1">
                        <button class="p5-btn p5-btn-ghost p5-btn-sm" type="submit">{{ __('notify.billing.dry_run_renewals') }}</button>
                    </form>
                @endcan
            </div>

            {{-- Review Queue --}}
            <div class="p5-card">
                <div class="p5-card-head">
                    <h2 class="p5-card-title">{{ __('notify.accounting.review_queue') }}</h2>
                    <span class="p5-kpi-meta">{{ $revenueRecognitionDashboard['review_queue']->count() }} جدول</span>
                </div>
                <div class="p5-list">
                    @forelse($revenueRecognitionDashboard['review_queue'] as $schedule)
                        <div class="p5-list-item">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap">
                                <div>
                                    <span class="p5-badge p5-badge-warning">{{ $schedule->policy }}</span>
                                    <strong style="font-size:13px;color:#0A1128;margin-inline-start:6px">{{ $schedule->invoice?->invoice_number }} · سطر {{ $schedule->invoice_line_id }}</strong>
                                    <span class="p5-kpi-meta" style="display:block;margin-top:2px">{{ \App\Support\Money::fromMinorUnits($schedule->original_recognizable_minor)->format() }} د.أ · حساب {{ $schedule->revenueAccount?->code }}</span>
                                </div>
                                @can(\App\Support\Permissions::RESOLVE_REVENUE_RECOGNITION_REVIEWS)
                                    @if($schedule->requires_manual_confirmation)
                                        <form method="POST" action="{{ route('accounting.revenue-recognition.confirm', $schedule) }}" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
                                            @csrf
                                            <input name="recognition_date" type="date" required value="{{ now()->toDateString() }}" class="p5-input" style="padding:4px 6px;font-size:11px">
                                            <input name="note" placeholder="ملاحظة إنجاز" class="p5-input" style="max-width:110px;padding:4px 6px;font-size:11px">
                                            <button class="p5-btn p5-btn-primary p5-btn-sm" type="submit">تأكيد</button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    @empty
                        <div class="p5-kpi-meta" style="padding:14px;text-align:center">لا توجد جداول تحتاج مراجعة يدوية حالياً.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </x-notify.collapsible-section>

    {{-- Chart of Accounts & Journal Register --}}
    <div class="p5-grid-2">
        {{-- Chart of Accounts --}}
        <x-notify.collapsible-section
            :title="__('notify.accounting.chart')"
            subtitle="شجرة الحسابات وقواعد الترحيل"
            :badge="$accounts->count().' حساب'"
            icon="layers"
            :open="true"
        >
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الحساب</th>
                            <th>النوع / الطبيعة</th>
                            <th>الترحيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accounts as $account)
                            <tr>
                                <td dir="ltr" style="text-align:right"><strong>{{ $account->code }}</strong></td>
                                <td>
                                    <strong>{{ $account->name_ar }}</strong>
                                    <span class="p5-kpi-meta">{{ $account->name_en }}@if($account->parent) · أب: {{ $account->parent->code }} @endif</span>
                                </td>
                                <td>{{ $account->account_type }} / {{ $account->normal_balance }}</td>
                                <td>
                                    <span class="p5-badge {{ $account->allow_direct_posting && $account->is_active ? 'p5-badge-success' : 'p5-badge-neutral' }}">
                                        {{ $account->allow_direct_posting ? 'مباشر' : 'رئيسي' }}
                                    </span>
                                    @can(\App\Support\Permissions::MANAGE_CHART_OF_ACCOUNTS)
                                        @if(! $account->is_system && $account->journalLines()->count() === 0 && $account->is_active)
                                            <form method="POST" action="{{ route('accounting.chart-accounts.archive', $account) }}" style="display:inline;margin-inline-start:4px" onsubmit="return confirm('أرشفة هذا الحساب غير المستخدم؟')">
                                                @csrf
                                                <button class="p5-btn p5-btn-ghost p5-btn-sm" type="submit">أرشفة</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-notify.collapsible-section>

        {{-- Journal Register --}}
        <x-notify.collapsible-section
            :title="__('notify.accounting.journal')"
            subtitle="قيود اليومية المزدوجة المتوازنة"
            :badge="$journalRegister->count().' قيد'"
            icon="book-open"
            :open="false"
        >
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <thead>
                        <tr>
                            <th>رقم القيد</th>
                            <th>التاريخ</th>
                            <th>الحدث / الوصف</th>
                            <th>الإجمالي</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($journalRegister as $row)
                            @php
                                $entry = $row['entry'];
                            @endphp
                            <tr>
                                <td dir="ltr" style="text-align:right"><strong>{{ $entry->journal_number }}</strong></td>
                                <td>{{ $entry->entry_date?->format('Y-m-d') }}</td>
                                <td>
                                    <strong>{{ $entry->event_type }}</strong>
                                    <span class="p5-kpi-meta">{{ $entry->description }}</span>
                                </td>
                                <td>{{ \App\Support\Money::fromMinorUnits($row['total_debit_minor'])->format() }} د.أ</td>
                                <td>
                                    <span class="p5-badge {{ $entry->status === 'reversed' ? 'p5-badge-danger' : 'p5-badge-primary' }}">
                                        {{ $entry->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p5-kpi-meta" style="text-align:center">لا توجد قيود مسجلة بعد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-notify.collapsible-section>
    </div>

    {{-- Trial Balance Breakdown & Account Ledger --}}
    <div class="p5-grid-2">
        {{-- Trial Balance --}}
        <x-notify.collapsible-section
            :title="__('notify.accounting.trial_balance')"
            subtitle="تشخيص أرصدة الحسابات وحركات المدين والدائن"
            icon="scale"
            :open="false"
        >
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <thead>
                        <tr>
                            <th>الحساب</th>
                            <th>مدين</th>
                            <th>دائن</th>
                            <th>الصافي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trialBalance['rows'] as $row)
                            @if($row['debit_minor'] !== 0 || $row['credit_minor'] !== 0)
                                <tr>
                                    <td><strong>{{ $row['account']->code }}</strong> · {{ $row['account']->name_ar }}</td>
                                    <td>{{ \App\Support\Money::fromMinorUnits($row['debit_minor'])->format() }}</td>
                                    <td>{{ \App\Support\Money::fromMinorUnits($row['credit_minor'])->format() }}</td>
                                    <td><strong>{{ \App\Support\Money::fromMinorUnits(abs($row['net_minor']))->format() }}</strong></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-notify.collapsible-section>

        {{-- Account Ledger --}}
        <x-notify.collapsible-section
            :title="__('notify.accounting.account_ledger')"
            subtitle="{{ optional($selectedAccount)->code }} - حركات الحساب والرصيد التراكمي"
            icon="file-text"
            :open="false"
        >
            <form method="GET" action="{{ route('accounting.index') }}" style="margin-bottom:12px">
                <select name="account_id" onchange="this.form.submit()" class="p5-select">
                    @foreach($accounts->where('allow_direct_posting', true) as $account)
                        <option value="{{ $account->id }}" @selected($selectedAccount && $selectedAccount->id === $account->id)>{{ $account->code }} · {{ $account->name_ar }}</option>
                    @endforeach
                </select>
            </form>
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <thead>
                        <tr>
                            <th>القيد</th>
                            <th>التاريخ</th>
                            <th>مدين</th>
                            <th>دائن</th>
                            <th>الرصيد التراكمي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledger as $row)
                            @php
                                $line = $row['line'];
                            @endphp
                            <tr>
                                <td dir="ltr" style="text-align:right"><strong>{{ $line->entry->journal_number }}</strong></td>
                                <td>{{ $line->entry->entry_date?->format('Y-m-d') }}</td>
                                <td>{{ \App\Support\Money::fromMinorUnits($line->debit_minor)->format() }}</td>
                                <td>{{ \App\Support\Money::fromMinorUnits($line->credit_minor)->format() }}</td>
                                <td><strong>{{ \App\Support\Money::fromMinorUnits(abs($row['running_balance_minor']))->format() }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p5-kpi-meta" style="text-align:center">لا توجد حركات مسجلة لهذا الحساب.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-notify.collapsible-section>
    </div>

    {{-- Accounting Periods & Cash Reconciliation --}}
    <div class="p5-grid-2">
        {{-- Accounting Periods --}}
        <x-notify.collapsible-section
            :title="__('notify.accounting.accounting_periods')"
            subtitle="حالات الفترات المفتوحة والمغلقة"
            :badge="$periods->count().' فترة'"
            icon="calendar"
            :open="false"
        >
            <div class="p5-list">
                @forelse($periods as $period)
                    <div class="p5-list-item">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
                            <div>
                                <span class="p5-badge {{ $period->status === 'open' ? 'p5-badge-success' : 'p5-badge-danger' }}">{{ $period->status === 'open' ? __('notify.statuses.open') : __('notify.statuses.closed') }}</span>
                                <strong style="font-size:14px;color:#0A1128;margin-inline-start:6px">{{ $period->period_key }}</strong>
                                <span class="p5-kpi-meta" style="display:block;margin-top:2px">{{ $period->start_date?->format('Y-m-d') }} ← {{ $period->end_date?->format('Y-m-d') }}</span>
                            </div>
                            @can(\App\Support\Permissions::MANAGE_ACCOUNTING_PERIODS)
                                @if($period->status === 'open')
                                    <form method="POST" action="{{ route('accounting.periods.close', $period) }}" style="display:flex;gap:4px">
                                        @csrf
                                        <input name="notes" placeholder="ملاحظات الإغلاق" class="p5-input" style="max-width:130px;padding:4px 6px;font-size:11px">
                                        <button class="p5-btn p5-btn-danger p5-btn-sm" type="submit">إغلاق الفترة</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('accounting.periods.reopen', $period) }}" style="display:flex;gap:4px">
                                        @csrf
                                        <input name="reason" required placeholder="سبب إعادة الفتح" class="p5-input" style="max-width:130px;padding:4px 6px;font-size:11px">
                                        <button class="p5-btn p5-btn-soft p5-btn-sm" type="submit">إعادة الفتح</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="p5-kpi-meta" style="padding:14px;text-align:center">سيتم إنشاء الفترات تلقائياً عند أول ترحيل محاسبي.</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>

        {{-- Cash Reconciliation --}}
        <x-notify.collapsible-section
            :title="__('notify.accounting.cash_reconciliation')"
            subtitle="تكامل النقد مع دفتر الأستاذ"
            :badge="$reconciliationResult['ok'] ? __('notify.statuses.reconciled') : __('notify.statuses.needs_review')"
            :badgeVariant="$reconciliationResult['ok'] ? 'success' : 'danger'"
            icon="check-square"
            :open="false"
        >
            <div class="p5-list">
                @foreach($reconciliationResult['cash_accounts'] as $row)
                    <div class="p5-list-item">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                            <div>
                                <span class="p5-badge {{ $row['difference_minor'] === 0 ? 'p5-badge-success' : 'p5-badge-danger' }}">
                                    {{ $row['difference_minor'] === 0 ? __('notify.statuses.reconciled') : __('notify.statuses.needs_review') }}
                                </span>
                                <strong style="font-size:13px;color:#0A1128;margin-inline-start:6px">{{ $row['financial_account']->name_ar }} ← حساب {{ $row['chart_account']->code }}</strong>
                                <span class="p5-kpi-meta" style="display:block;margin-top:2px">
                                    نقد تشغيلي: {{ \App\Support\Money::fromMinorUnits($row['cash_subledger_minor'])->format() }} · دفتر أستاذ: {{ \App\Support\Money::fromMinorUnits($row['general_ledger_minor'])->format() }} · الفارق: {{ \App\Support\Money::fromMinorUnits(abs($row['difference_minor']))->format() }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
                @if($reconciliationResult['failures'] !== [])
                    <div style="color:#B42318;font-size:12px;margin-top:8px">ملاحظات الفروقات: {{ implode(', ', $reconciliationResult['failures']) }}</div>
                @endif
            </div>
        </x-notify.collapsible-section>
    </div>

    {{-- Billing Reconciliation Table --}}
    <x-notify.collapsible-section
        :title="__('notify.accounting.billing_reconciliation')"
        subtitle="E2B schedule-aware - مطابقة الفواتير مع دفتر الأستاذ"
        icon="clipboard-check"
        :open="false"
    >
        <div class="p5-table-wrap">
            <table class="p5-table">
                <thead>
                    <tr>
                        <th>{{ __('notify.accounting.check') }}</th>
                        <th>الحساب</th>
                        <th>الرصيد التشغيلي</th>
                        <th>{{ __('notify.accounting.ledger') }}</th>
                        <th>الفارق</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($reconciliationResult['billing'] ?? []) as $key => $row)
                        <tr>
                            <td><strong>{{ str_replace('_', ' ', $key) }}</strong></td>
                            <td>{{ $row['chart_account']->code }} · {{ $row['chart_account']->name_ar }}</td>
                            <td>{{ \App\Support\Money::fromMinorUnits($row['operational_minor'])->format() }} د.أ</td>
                            <td>{{ \App\Support\Money::fromMinorUnits($row['general_ledger_minor'])->format() }} د.أ</td>
                            <td>
                                <span class="p5-badge {{ $row['difference_minor'] === 0 ? 'p5-badge-success' : 'p5-badge-danger' }}">
                                    {{ \App\Support\Money::fromMinorUnits(abs($row['difference_minor']))->format() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-notify.collapsible-section>

    {{-- Recognized Revenue Reconciliation Table --}}
    <x-notify.collapsible-section
        :title="__('notify.accounting.revenue_reconciliation')"
        subtitle="مطابقة الجداول التشغيلية مع الأستاذ العام"
        icon="award"
        :open="false"
    >
        <div class="p5-table-wrap">
            <table class="p5-table">
                <thead>
                    <tr>
                        <th>{{ __('notify.accounting.check') }}</th>
                        <th>الحساب</th>
                        <th>الرصيد التشغيلي</th>
                        <th>{{ __('notify.accounting.ledger') }}</th>
                        <th>الفارق</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($reconciliationResult['recognized_revenue'] ?? []) as $key => $row)
                        <tr>
                            <td><strong>{{ str_replace('_', ' ', $key) }}</strong></td>
                            <td>{{ $row['chart_account']->code }} · {{ $row['chart_account']->name_ar }}</td>
                            <td>{{ \App\Support\Money::fromMinorUnits($row['operational_minor'])->format() }} د.أ</td>
                            <td>{{ \App\Support\Money::fromMinorUnits($row['general_ledger_minor'])->format() }} د.أ</td>
                            <td>
                                <span class="p5-badge {{ $row['difference_minor'] === 0 ? 'p5-badge-success' : 'p5-badge-danger' }}">
                                    {{ \App\Support\Money::fromMinorUnits(abs($row['difference_minor']))->format() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-notify.collapsible-section>

    {{-- Recognition Schedules Table --}}
    <x-notify.collapsible-section
        :title="__('notify.accounting.revenue_schedules')"
        subtitle="سجل جداول الاعتراف بالإيراد وحالات الاستحقاق"
        :badge="$revenueRecognitionDashboard['schedule_rows']->count().' جدول'"
        icon="list"
        :open="false"
    >
        <div class="p5-table-wrap">
            <table class="p5-table">
                <thead>
                    <tr>
                        <th>الفاتورة / السطر</th>
                        <th>السياسة</th>
                        <th>الأصلي</th>
                        <th>المعترف به</th>
                        <th>المؤجل المستقبلي</th>
                        <th>تعديلات الدائن</th>
                        <th>تاريخ الاستحقاق</th>
                        <th>الحالة</th>
                        <th>حساب الإيراد</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($revenueRecognitionDashboard['schedule_rows'] as $row)
                        <tr>
                            <td>{{ $row['invoice_number'] }} / {{ $row['invoice_line_id'] }}</td>
                            <td><span class="p5-badge p5-badge-primary">{{ $row['policy'] }}</span></td>
                            <td>{{ \App\Support\Money::fromMinorUnits($row['original_recognizable_minor'])->format() }}</td>
                            <td>{{ \App\Support\Money::fromMinorUnits($row['recognized_minor'])->format() }}</td>
                            <td>{{ \App\Support\Money::fromMinorUnits($row['future_deferred_minor'])->format() }}</td>
                            <td>{{ \App\Support\Money::fromMinorUnits($row['adjustments_minor'])->format() }}</td>
                            <td>{{ $row['next_recognition_date']?->format('Y-m-d') ?? '—' }}</td>
                            <td>
                                <span class="p5-badge {{ $row['status'] === 'needs_review' ? 'p5-badge-warning' : 'p5-badge-success' }}">
                                    {{ $row['status'] }}
                                </span>
                            </td>
                            <td>{{ $row['revenue_account_code'] }} · {{ $row['revenue_account_name_ar'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p5-kpi-meta" style="text-align:center">لا توجد جداول اعتراف بالإيراد مسجلة بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-notify.collapsible-section>
</div>
@endsection
