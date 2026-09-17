@extends('layouts.app')

@section('content')
<div class="p4-wrap">
    {{-- Header --}}
    <header class="p4-header">
        <div class="p4-header-main">
            <div class="p4-eyebrow">Finance / Cash Management</div>
            <h1 class="p4-title">{{ __('notify.accounts.title') }}</h1>
            <p class="p4-subtitle">حسابات تشغيلية، حركات نقدية غير قابلة للتعديل، تحويلات داخلية، وتعيين الحركات النقدية التاريخية.</p>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                <a href="{{ route('finance.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">تقارير الإدارة المالية</a>
                <a href="{{ route('operating-expenses.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">المصاريف التشغيلية</a>
                <a href="{{ route('capital-management.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">التمويل والأصول</a>
            </div>
        </div>
    </header>

    {{-- KPIs --}}
    <div class="p4-kpis">
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">{{ __('notify.accounts.cash') }}</span>
            <span class="p4-kpi-value is-success">{{ \App\Support\Money::fromMinorUnits($totalOperationalCashMinor)->format() }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p4-kpi-meta">رصيد الحسابات النشطة المعتمدة</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">{{ __('notify.accounts.inflows_today') }}</span>
            <span class="p4-kpi-value is-primary">{{ \App\Support\Money::fromMinorUnits($todayInflowsMinor)->format() }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p4-kpi-meta">دخول نقدي تشغيلي اليوم</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">{{ __('notify.accounts.outflows_today') }}</span>
            <span class="p4-kpi-value is-warning">{{ \App\Support\Money::fromMinorUnits($todayOutflowsMinor)->format() }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p4-kpi-meta">خروج نقدي اليوم</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">أحداث نقدية غير معينة</span>
            <span class="p4-kpi-value {{ $unassignedCount > 0 ? 'is-danger' : 'is-success' }}">{{ $unassignedCount }}</span>
            <span class="p4-kpi-meta">تحتاج تعيين حساب مالي</span>
        </div>
    </div>

    {{-- 1. Accounts List --}}
    <x-notify.collapsible-section id="sec-accounts-list" :title="__('notify.accounts.accounts_list')" subtitle="الحسابات النقدية والبنكية والمحافظ التشغيلية النشطة" :badge="$accountCards->count()" :open="true">
        <div class="p4-list">
            @forelse($accountCards as $card)
                @php
                    $account = $card['account'];
                @endphp
                <div class="p4-list-item">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
                        <div>
                            <div style="display:flex;gap:6px;align-items:center">
                                <strong style="font-size:15px;color:#0A1128">{{ $account->name_ar }}</strong>
                                <span class="p4-kpi-meta">({{ $account->code }})</span>
                                <span class="p4-badge {{ $account->is_active ? 'p4-badge-success' : 'p4-badge-danger' }}">{{ $account->is_active ? 'نشط' : 'مؤرشف' }}</span>
                            </div>
                            <span style="font-size:12px;color:#475569;display:block;margin-top:2px">{{ $account->type }} · {{ $account->currency }}</span>
                            <div style="margin-top:6px;font-size:13px">
                                <strong>الرصيد: {{ \App\Support\Money::fromMinorUnits($card['balance_minor'])->format() }} د.أ</strong>
                                <span class="p4-kpi-meta">دخول اليوم: {{ \App\Support\Money::fromMinorUnits($card['today_inflows_minor'])->format() }} · خروج اليوم: {{ \App\Support\Money::fromMinorUnits($card['today_outflows_minor'])->format() }}</span>
                            </div>
                        </div>
                        @can(\App\Support\FinancialPermissions::MANAGE_FINANCIAL_ACCOUNTS)
                            @if($account->is_active)
                                <form method="POST" action="{{ route('financial-accounts.archive', $account) }}" onsubmit="return confirm('سيتم أرشفة الحساب مع بقاء السجل التاريخي. هل تريد المتابعة؟')">
                                    @csrf
                                    <button class="p4-btn p4-btn-ghost p4-btn-sm" type="submit">أرشفة الحساب</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <div class="p4-kpi-meta" style="padding:14px;text-align:center">لا توجد حسابات مالية بعد.</div>
            @endforelse
        </div>
    </x-notify.collapsible-section>

    {{-- 2. Create Financial Account Form --}}
    @can(\App\Support\FinancialPermissions::MANAGE_FINANCIAL_ACCOUNTS)
        <x-notify.collapsible-section id="sec-create-account" title="إنشاء حساب مالي جديد" subtitle="حساب بنكي، صندوق نقد، أو محفظة" :open="false">
            <form method="POST" action="{{ route('financial-accounts.store') }}">
                @csrf
                <div class="p4-form-grid">
                    <div class="p4-field">
                        <label>الكود *</label>
                        <input name="code" required placeholder="cash_box" class="p4-input">
                    </div>
                    <div class="p4-field">
                        <label>الاسم العربي *</label>
                        <input name="name_ar" required placeholder="صندوق الشركة" class="p4-input">
                    </div>
                    <div class="p4-field">
                        <label>النوع *</label>
                        <select name="type" required class="p4-select">
                            <option value="cash">{{ __('notify.accounts.type_cash') }}</option>
                            <option value="bank">{{ __('notify.accounts.type_bank') }}</option>
                            <option value="wallet">{{ __('notify.accounts.type_wallet') }}</option>
                            <option value="clearing">{{ __('notify.accounts.type_clearing') }}</option>
                            <option value="other">{{ __('notify.accounts.type_other') }}</option>
                        </select>
                    </div>
                    <div class="p4-field">
                        <label>الرصيد الافتتاحي</label>
                        <input name="opening_balance" value="0.000" class="p4-input">
                    </div>
                    <div class="p4-field">
                        <label>تاريخ الرصيد</label>
                        <input type="datetime-local" name="opening_date" value="{{ now()->format('Y-m-d\\TH:i') }}" class="p4-input">
                    </div>
                    <div class="p4-field" style="grid-column: 1 / -1">
                        <label>ملاحظات</label>
                        <textarea name="notes" rows="2" class="p4-textarea"></textarea>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:12px">
                    <button class="p4-btn p4-btn-primary" type="submit">إنشاء الحساب</button>
                </div>
            </form>
        </x-notify.collapsible-section>
    @endcan

    <div class="p4-grid-2">
        {{-- 3. Internal Transfer Form --}}
        @can(\App\Support\FinancialPermissions::MANAGE_CASH_TRANSFERS)
            <x-notify.collapsible-section id="sec-internal-transfer" :title="__('notify.accounts.internal_transfer')" subtitle="تحويل مباشر دون تأثير على الأرباح أو الإيرادات" :open="false">
                <form method="POST" action="{{ route('financial-transfers.store') }}">
                    @csrf
                    <div class="p4-form-grid">
                        <div class="p4-field">
                            <label>من حساب *</label>
                            <select name="from_financial_account_id" required class="p4-select">
                                @foreach($activeAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p4-field">
                            <label>إلى حساب *</label>
                            <select name="to_financial_account_id" required class="p4-select">
                                @foreach($activeAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p4-field">
                            <label>المبلغ (د.أ) *</label>
                            <input name="amount" required placeholder="10.000" class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>وقت التحويل *</label>
                            <input type="datetime-local" name="transferred_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required class="p4-input">
                        </div>
                        <div class="p4-field" style="grid-column: 1 / -1">
                            <label>المرجع</label>
                            <input name="reference" class="p4-input" placeholder="رقم إشعار التحويل">
                        </div>
                        <div class="p4-field" style="grid-column: 1 / -1">
                            <label>ملاحظات</label>
                            <textarea name="notes" rows="2" class="p4-textarea"></textarea>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:12px">
                        <button class="p4-btn p4-btn-primary" type="submit">تسجيل التحويل</button>
                    </div>
                </form>
            </x-notify.collapsible-section>
        @endcan

        {{-- 4. Recent Transfers List --}}
        <x-notify.collapsible-section id="sec-recent-transfers" :title="__('notify.accounts.recent_transfers')" subtitle="سجل التحويلات المالية الداخلية" :badge="$transfers->count()" :open="false">
            <div class="p4-list">
                @forelse($transfers as $transfer)
                    <div class="p4-list-item">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
                            <div>
                                <div style="display:flex;gap:6px;align-items:center">
                                    <strong style="font-size:14px;color:#0A1128" dir="ltr">{{ $transfer->transfer_number }}</strong>
                                    <span class="p4-badge {{ $transfer->reversal ? 'p4-badge-danger' : 'p4-badge-primary' }}">{{ $transfer->reversal ? 'معكوس' : 'ناجح' }}</span>
                                </div>
                                <span style="font-size:13px;color:#0055CC;font-weight:700;display:block;margin-top:3px">
                                    {{ optional($transfer->fromAccount)->name_ar }} → {{ optional($transfer->toAccount)->name_ar }} · {{ \App\Support\Money::fromMinorUnits($transfer->amount_minor)->format() }} د.أ
                                </span>
                                <span class="p4-kpi-meta">{{ $transfer->transferred_at?->format('Y-m-d H:i') }}@if($transfer->reference) · {{ $transfer->reference }} @endif</span>
                                @if($transfer->reversal)
                                    <span style="display:block;font-size:11px;color:#B42318;margin-top:3px">سبب العكس: {{ $transfer->reversal->reason }}</span>
                                @endif
                            </div>
                            @if(!$transfer->reversal)
                                @can(\App\Support\FinancialPermissions::MANAGE_CASH_TRANSFERS)
                                    <form method="POST" action="{{ route('financial-transfers.reverse', $transfer) }}" onsubmit="return confirm('سيتم إنشاء حركات عكسية مساوية دون حذف التحويل الأصلي. هل تريد المتابعة؟')" style="display:flex;gap:4px">
                                        @csrf
                                        <input name="reason" required placeholder="سبب العكس" class="p4-input" style="max-width:130px;padding:4px 6px;font-size:12px">
                                        <button class="p4-btn p4-btn-danger p4-btn-sm" type="submit">عكس</button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p4-kpi-meta" style="padding:14px;text-align:center">{{ __('notify.accounts.empty_transfers') }}</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>
    </div>

    {{-- Unassigned Cash Events & Recent Movements --}}
    <div class="p4-grid-2">
        {{-- 5. Unassigned Events --}}
        <x-notify.collapsible-section id="sec-unassigned-events" :title="__('notify.accounts.unassigned_events')" subtitle="حركات نقدية تاريخية تحتاج تعيين حساب مالي" :badge="$unassignedCount" :badgeVariant="$unassignedCount > 0 ? 'danger' : 'neutral'" :open="$unassignedCount > 0">
            <div class="p4-list">
                @foreach($unassignedPayments as $payment)
                    <div class="p4-list-item">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                            <div>
                                <span class="p4-badge p4-badge-warning">قبض دفع</span>
                                <strong style="font-size:14px;color:#0A1128;margin-inline-start:6px">{{ \App\Support\Money::fromMinorUnits($payment->amount_minor)->format() }} د.أ</strong>
                                <span class="p4-kpi-meta" style="display:block;margin-top:2px">{{ optional($payment->client)->business_name }} · {{ $payment->received_at?->format('Y-m-d H:i') }} · {{ $payment->payment_method }} @if($payment->reference) · {{ $payment->reference }} @endif</span>
                            </div>
                            @can(\App\Support\FinancialPermissions::ASSIGN_HISTORICAL_CASH_ACCOUNTS)
                                <form method="POST" action="{{ route('cash-events.assign-account') }}" style="display:flex;gap:4px;align-items:center">
                                    @csrf
                                    <input type="hidden" name="event_type" value="payment">
                                    <input type="hidden" name="event_id" value="{{ $payment->id }}">
                                    <select name="financial_account_id" required class="p4-select" style="font-size:12px;padding:4px 8px">
                                        @foreach($activeAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                        @endforeach
                                    </select>
                                    <button class="p4-btn p4-btn-primary p4-btn-sm" type="submit">تعيين</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
                @foreach($unassignedRefunds as $refund)
                    <div class="p4-list-item">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                            <div>
                                <span class="p4-badge p4-badge-danger">استرداد</span>
                                <strong style="font-size:14px;color:#0A1128;margin-inline-start:6px">{{ \App\Support\Money::fromMinorUnits($refund->amount_minor)->format() }} د.أ</strong>
                                <span class="p4-kpi-meta" style="display:block;margin-top:2px">{{ optional($refund->client)->business_name }} · {{ $refund->refunded_at?->format('Y-m-d H:i') }} · {{ $refund->refund_method }} @if($refund->reference) · {{ $refund->reference }} @endif</span>
                            </div>
                            @can(\App\Support\FinancialPermissions::ASSIGN_HISTORICAL_CASH_ACCOUNTS)
                                <form method="POST" action="{{ route('cash-events.assign-account') }}" style="display:flex;gap:4px;align-items:center">
                                    @csrf
                                    <input type="hidden" name="event_type" value="refund">
                                    <input type="hidden" name="event_id" value="{{ $refund->id }}">
                                    <select name="financial_account_id" required class="p4-select" style="font-size:12px;padding:4px 8px">
                                        @foreach($activeAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                        @endforeach
                                    </select>
                                    <button class="p4-btn p4-btn-primary p4-btn-sm" type="submit">تعيين</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
                @if($unassignedCount === 0)
                    <div class="p4-kpi-meta" style="padding:14px;text-align:center">{{ __('notify.accounts.empty_unassigned') }}</div>
                @endif
            </div>
        </x-notify.collapsible-section>

        {{-- 6. Recent Cash Movements --}}
        <x-notify.collapsible-section id="sec-recent-movements" :title="__('notify.accounts.recent_movements')" subtitle="سجل الحركات النقدية اللحظية" :badge="$recentMovements->count()" :open="false">
            <div class="p4-list">
                @forelse($recentMovements as $movement)
                    <div class="p4-list-item">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                            <div>
                                <span class="p4-badge {{ $movement->direction === 'inflow' ? 'p4-badge-success' : 'p4-badge-danger' }}">
                                    {{ $movement->direction === 'inflow' ? 'قبض دخول' : 'صرف خروج' }}
                                </span>
                                <strong style="font-size:14px;color:#0A1128;margin-inline-start:6px">{{ \App\Support\Money::fromMinorUnits($movement->amount_minor)->format() }} د.أ</strong>
                                <span style="font-size:12px;color:#0055CC;font-weight:700">· {{ optional($movement->financialAccount)->name_ar }}</span>
                                <span class="p4-kpi-meta" style="display:block;margin-top:2px">{{ $movement->event_type }} · {{ $movement->occurred_at?->format('Y-m-d H:i') }}</span>
                                @if($movement->description)<span class="p4-kpi-meta" style="display:block">{{ $movement->description }}</span>@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p4-kpi-meta" style="padding:14px;text-align:center">{{ __('notify.accounts.empty_movements') }}</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>
    </div>
</div>
@endsection
