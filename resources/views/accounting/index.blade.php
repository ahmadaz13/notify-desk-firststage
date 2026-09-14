@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">Finance / Accounting</div>
        <h1 class="page-title">المحاسبة العامة</h1>
        <div class="muted" style="margin-top:5px">قيود مزدوجة غير قابلة للتعديل، دفتر أستاذ، ميزان مراجعة، وفترات محاسبية.</div>
    </div>
    @can(\App\Support\FinancialPermissions::RUN_ACCOUNTING_BACKFILL)
        <form method="POST" action="{{ route('accounting.backfill') }}">
            @csrf
            <input type="hidden" name="dry_run" value="1">
            <button class="btn btn-ghost" type="submit">Dry-run Backfill</button>
        </form>
    @endcan
    @can(\App\Support\FinancialPermissions::RUN_REVENUE_RECOGNITION)
        <form method="POST" action="{{ route('accounting.revenue-schedules.backfill') }}">
            @csrf
            <input type="hidden" name="dry_run" value="1">
            <button class="btn btn-ghost" type="submit">Dry-run Revenue Schedules</button>
        </form>
    @endcan
</div>

<div class="card" style="margin-bottom:16px;border-color:var(--nd-warning)">
    <strong>Revenue recognition policy</strong>
    <div class="muted" style="margin-top:6px">في E2B يتم الاعتراف بالإيراد من جداول أداء الخدمة وليس من الدفعات أو إصدار الفاتورة. هذه الصفحة تعرض تشخيصات محرك المحاسبة فقط؛ MRR/ARR و P&amp;L النهائي ليست من مخرجات هذه المرحلة.</div>
</div>

<div class="grid grid-4" style="margin-bottom:16px">
    <div class="card">
        <div class="kpi-label">Trial Balance Debit</div>
        <div class="kpi-value" style="font-size:18px">{{ \App\Support\Money::fromMinorUnits($trialBalance['total_debit_minor'])->format() }} د.أ</div>
    </div>
    <div class="card">
        <div class="kpi-label">Trial Balance Credit</div>
        <div class="kpi-value" style="font-size:18px">{{ \App\Support\Money::fromMinorUnits($trialBalance['total_credit_minor'])->format() }} د.أ</div>
    </div>
    <div class="card">
        <div class="kpi-label">Difference</div>
        <div class="kpi-value" style="font-size:18px;color:{{ $trialBalance['difference_minor'] === 0 ? 'var(--nd-success)' : 'var(--nd-danger)' }}">{{ \App\Support\Money::fromMinorUnits(abs($trialBalance['difference_minor']))->format() }} د.أ</div>
    </div>
    <div class="card">
        <div class="kpi-label">Reconciliation</div>
        <div class="kpi-value" style="font-size:18px;color:{{ $reconciliationResult['ok'] ? 'var(--nd-success)' : 'var(--nd-danger)' }}">{{ $reconciliationResult['ok'] ? 'OK' : 'Needs Review' }}</div>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px">
    <div class="card">
        <div class="kpi-label">Revenue Schedules</div>
        <div class="kpi-value" style="font-size:18px">{{ $revenueRecognitionDashboard['schedule_count'] }}</div>
    </div>
    <div class="card">
        <div class="kpi-label">Due for Recognition</div>
        <div class="kpi-value" style="font-size:18px">{{ $revenueRecognitionDashboard['due_period_count'] }}</div>
    </div>
    <div class="card">
        <div class="kpi-label">Review Queue</div>
        <div class="kpi-value" style="font-size:18px;color:{{ $revenueRecognitionDashboard['needs_review_count'] === 0 ? 'var(--nd-success)' : 'var(--nd-warning)' }}">{{ $revenueRecognitionDashboard['needs_review_count'] }}</div>
    </div>
    <div class="card">
        <div class="kpi-label">Deferred Remaining</div>
        <div class="kpi-value" style="font-size:18px">{{ \App\Support\Money::fromMinorUnits($revenueRecognitionDashboard['deferred_remaining_minor'])->format() }} د.أ</div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Revenue Recognition</h2>
            <span class="muted">diagnostics</span>
        </div>
        <div class="list">
            <div class="list-row">
                <span class="badge blue">SaaS</span>
                <div class="list-main">
                    <strong>{{ \App\Support\Money::fromMinorUnits($revenueRecognitionDashboard['recognized_saas_mtd_minor'])->format() }} د.أ</strong>
                    <small>Recognized SaaS revenue month-to-date</small>
                </div>
            </div>
            <div class="list-row">
                <span class="badge blue">Service</span>
                <div class="list-main">
                    <strong>{{ \App\Support\Money::fromMinorUnits($revenueRecognitionDashboard['recognized_one_time_mtd_minor'])->format() }} د.أ</strong>
                    <small>Recognized one-time revenue month-to-date</small>
                </div>
            </div>
        </div>
        @can(\App\Support\FinancialPermissions::RUN_REVENUE_RECOGNITION)
            <form method="POST" action="{{ route('accounting.revenue-recognition.run') }}" style="margin-top:12px">
                @csrf
                <input name="through" type="date" value="{{ now()->toDateString() }}">
                <input type="hidden" name="dry_run" value="1">
                <button class="btn btn-ghost" type="submit">Dry-run Recognition</button>
            </form>
        @endcan
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Recognition Review Queue</h2>
            <span class="muted">{{ $revenueRecognitionDashboard['review_queue']->count() }}</span>
        </div>
        <div class="list">
            @forelse($revenueRecognitionDashboard['review_queue'] as $schedule)
                <div class="list-row">
                    <span class="badge orange">{{ $schedule->policy }}</span>
                    <div class="list-main">
                        <strong>{{ $schedule->invoice?->invoice_number }} · line {{ $schedule->invoice_line_id }}</strong>
                        <small>{{ \App\Support\Money::fromMinorUnits($schedule->original_recognizable_minor)->format() }} د.أ · {{ $schedule->revenueAccount?->code }}</small>
                        @can(\App\Support\FinancialPermissions::RESOLVE_REVENUE_RECOGNITION_REVIEWS)
                            @if($schedule->requires_manual_confirmation)
                                <form method="POST" action="{{ route('accounting.revenue-recognition.confirm', $schedule) }}" style="margin-top:8px">
                                    @csrf
                                    <input name="recognition_date" type="date" required value="{{ now()->toDateString() }}">
                                    <input name="note" placeholder="Completion note">
                                    <button class="btn btn-ghost" type="submit">Confirm</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <div class="muted">لا توجد جداول تحتاج مراجعة حالياً.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Chart of Accounts</h2>
            <span class="muted">{{ $accounts->count() }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account</th>
                        <th>Type</th>
                        <th>Posting</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr>
                            <td class="ltr">{{ $account->code }}</td>
                            <td>
                                <strong>{{ $account->name_ar }}</strong>
                                <div class="muted">{{ $account->name_en }}@if($account->parent) · parent {{ $account->parent->code }} @endif</div>
                            </td>
                            <td>{{ $account->account_type }} / {{ $account->normal_balance }}</td>
                            <td>
                                <span class="badge {{ $account->allow_direct_posting && $account->is_active ? 'green' : 'gray' }}">{{ $account->allow_direct_posting ? 'direct' : 'header' }}</span>
                                @can(\App\Support\FinancialPermissions::MANAGE_CHART_OF_ACCOUNTS)
                                    @if(! $account->is_system && $account->journalLines()->count() === 0 && $account->is_active)
                                        <form method="POST" action="{{ route('accounting.chart-accounts.archive', $account) }}" style="display:inline" onsubmit="return confirm('Archive this unused chart account?')">
                                            @csrf
                                            <button class="btn btn-ghost" type="submit">Archive</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Journal Register</h2>
            <span class="muted">{{ $journalRegister->count() }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Journal</th>
                        <th>Date</th>
                        <th>Event</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($journalRegister as $row)
                        @php($entry = $row['entry'])
                        <tr>
                            <td class="ltr">{{ $entry->journal_number }}</td>
                            <td>{{ $entry->entry_date?->format('Y-m-d') }}</td>
                            <td>
                                <strong>{{ $entry->event_type }}</strong>
                                <div class="muted">{{ $entry->description }}</div>
                            </td>
                            <td>{{ \App\Support\Money::fromMinorUnits($row['total_debit_minor'])->format() }} د.أ</td>
                            <td><span class="badge {{ $entry->status === 'reversed' ? 'red' : 'blue' }}">{{ $entry->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">لا توجد قيود بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Trial Balance</h2>
            <span class="muted">Not a balance sheet</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($trialBalance['rows'] as $row)
                        @if($row['debit_minor'] !== 0 || $row['credit_minor'] !== 0)
                            <tr>
                                <td>{{ $row['account']->code }} · {{ $row['account']->name_ar }}</td>
                                <td>{{ \App\Support\Money::fromMinorUnits($row['debit_minor'])->format() }}</td>
                                <td>{{ \App\Support\Money::fromMinorUnits($row['credit_minor'])->format() }}</td>
                                <td>{{ \App\Support\Money::fromMinorUnits(abs($row['net_minor']))->format() }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Account Ledger</h2>
            <span class="muted">{{ optional($selectedAccount)->code }}</span>
        </div>
        <form method="GET" action="{{ route('accounting.index') }}" style="margin-bottom:10px">
            <select name="account_id" onchange="this.form.submit()">
                @foreach($accounts->where('allow_direct_posting', true) as $account)
                    <option value="{{ $account->id }}" @selected($selectedAccount && $selectedAccount->id === $account->id)>{{ $account->code }} · {{ $account->name_ar }}</option>
                @endforeach
            </select>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Journal</th>
                        <th>Date</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledger as $row)
                        @php($line = $row['line'])
                        <tr>
                            <td class="ltr">{{ $line->entry->journal_number }}</td>
                            <td>{{ $line->entry->entry_date?->format('Y-m-d') }}</td>
                            <td>{{ \App\Support\Money::fromMinorUnits($line->debit_minor)->format() }}</td>
                            <td>{{ \App\Support\Money::fromMinorUnits($line->credit_minor)->format() }}</td>
                            <td>{{ \App\Support\Money::fromMinorUnits(abs($row['running_balance_minor']))->format() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">لا توجد حركة لهذا الحساب.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Accounting Periods</h2>
            <span class="muted">{{ $periods->count() }}</span>
        </div>
        <div class="list">
            @forelse($periods as $period)
                <div class="list-row">
                    <span class="badge {{ $period->status === 'open' ? 'green' : 'red' }}">{{ $period->status }}</span>
                    <div class="list-main">
                        <strong>{{ $period->period_key }}</strong>
                        <small>{{ $period->start_date?->format('Y-m-d') }} → {{ $period->end_date?->format('Y-m-d') }}</small>
                        @can(\App\Support\FinancialPermissions::MANAGE_ACCOUNTING_PERIODS)
                            @if($period->status === 'open')
                                <form method="POST" action="{{ route('accounting.periods.close', $period) }}" style="margin-top:8px">
                                    @csrf
                                    <input name="notes" placeholder="ملاحظات الإغلاق">
                                    <button class="btn btn-ghost" type="submit">Close</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('accounting.periods.reopen', $period) }}" style="margin-top:8px">
                                    @csrf
                                    <input name="reason" required placeholder="سبب إعادة الفتح">
                                    <button class="btn btn-ghost" type="submit">Reopen</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <div class="muted">سيتم إنشاء الفترات تلقائياً عند أول ترحيل.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Reconciliation</h2>
            <span class="muted">{{ $reconciliationResult['ok'] ? 'clear' : 'review' }}</span>
        </div>
        <div class="list">
            @foreach($reconciliationResult['cash_accounts'] as $row)
                <div class="list-row">
                    <span class="badge {{ $row['difference_minor'] === 0 ? 'green' : 'red' }}">{{ $row['difference_minor'] === 0 ? 'OK' : 'Diff' }}</span>
                    <div class="list-main">
                        <strong>{{ $row['financial_account']->name_ar }} → {{ $row['chart_account']->code }}</strong>
                        <small>Cash {{ \App\Support\Money::fromMinorUnits($row['cash_subledger_minor'])->format() }} · GL {{ \App\Support\Money::fromMinorUnits($row['general_ledger_minor'])->format() }} · Difference {{ \App\Support\Money::fromMinorUnits(abs($row['difference_minor']))->format() }}</small>
                    </div>
                </div>
            @endforeach
            @if($reconciliationResult['failures'] !== [])
                <div class="muted" style="color:var(--nd-danger)">Failures: {{ implode(', ', $reconciliationResult['failures']) }}</div>
            @endif
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-head" style="margin-top:0">
        <h2>Billing Reconciliation</h2>
        <span class="muted">E2B schedule-aware</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Check</th>
                    <th>Account</th>
                    <th>Operational</th>
                    <th>General Ledger</th>
                    <th>Difference</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($reconciliationResult['billing'] ?? []) as $key => $row)
                    <tr>
                        <td>{{ str_replace('_', ' ', $key) }}</td>
                        <td>{{ $row['chart_account']->code }} · {{ $row['chart_account']->name_ar }}</td>
                        <td>{{ \App\Support\Money::fromMinorUnits($row['operational_minor'])->format() }} د.أ</td>
                        <td>{{ \App\Support\Money::fromMinorUnits($row['general_ledger_minor'])->format() }} د.أ</td>
                        <td>
                            <span class="badge {{ $row['difference_minor'] === 0 ? 'green' : 'red' }}">
                                {{ \App\Support\Money::fromMinorUnits(abs($row['difference_minor']))->format() }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-head" style="margin-top:0">
        <h2>Recognized Revenue Reconciliation</h2>
        <span class="muted">not final P&amp;L</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Check</th>
                    <th>Account</th>
                    <th>Operational</th>
                    <th>General Ledger</th>
                    <th>Difference</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($reconciliationResult['recognized_revenue'] ?? []) as $key => $row)
                    <tr>
                        <td>{{ str_replace('_', ' ', $key) }}</td>
                        <td>{{ $row['chart_account']->code }} · {{ $row['chart_account']->name_ar }}</td>
                        <td>{{ \App\Support\Money::fromMinorUnits($row['operational_minor'])->format() }} د.أ</td>
                        <td>{{ \App\Support\Money::fromMinorUnits($row['general_ledger_minor'])->format() }} د.أ</td>
                        <td>
                            <span class="badge {{ $row['difference_minor'] === 0 ? 'green' : 'red' }}">
                                {{ \App\Support\Money::fromMinorUnits(abs($row['difference_minor']))->format() }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-head" style="margin-top:0">
        <h2>Recognition Schedules</h2>
        <span class="muted">{{ $revenueRecognitionDashboard['schedules']->count() }}</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Invoice / Line</th>
                    <th>Policy</th>
                    <th>Original</th>
                    <th>Recognized</th>
                    <th>Future Deferred</th>
                    <th>Credit Adjustments</th>
                    <th>Next Date</th>
                    <th>Status</th>
                    <th>Revenue Account</th>
                </tr>
            </thead>
            <tbody>
                @forelse($revenueRecognitionDashboard['schedules'] as $schedule)
                    @php
                        $recognized = $schedule->periods->sum('recognized_minor');
                        $futureAdjustments = $schedule->adjustments->where('adjustment_type', \App\Models\RevenueRecognitionAdjustment::TYPE_FUTURE_DEFERRED_REDUCTION)->sum('amount_minor');
                        $futureDeferred = max(0, $schedule->original_recognizable_minor - $recognized - $futureAdjustments);
                        $nextPeriod = $schedule->periods->where('status', \App\Models\RevenueRecognitionPeriod::STATUS_PENDING)->sortBy('period_end')->first();
                    @endphp
                    <tr>
                        <td>{{ $schedule->invoice?->invoice_number }} / {{ $schedule->invoice_line_id }}</td>
                        <td>{{ $schedule->policy }}</td>
                        <td>{{ \App\Support\Money::fromMinorUnits($schedule->original_recognizable_minor)->format() }}</td>
                        <td>{{ \App\Support\Money::fromMinorUnits($recognized)->format() }}</td>
                        <td>{{ \App\Support\Money::fromMinorUnits($futureDeferred)->format() }}</td>
                        <td>{{ \App\Support\Money::fromMinorUnits($schedule->adjustments->sum('amount_minor'))->format() }}</td>
                        <td>{{ $nextPeriod?->period_end?->format('Y-m-d') ?? '—' }}</td>
                        <td><span class="badge {{ $schedule->status === 'needs_review' ? 'orange' : 'green' }}">{{ $schedule->status }}</span></td>
                        <td>{{ $schedule->revenueAccount?->code }} · {{ $schedule->revenueAccount?->name_ar }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">لا توجد جداول اعتراف بالإيراد بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
