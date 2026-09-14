@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">Finance / Cash Management</div>
        <h1 class="page-title">إدارة النقد والحسابات المالية</h1>
        <div class="muted" style="margin-top:5px">حسابات تشغيلية، حركات نقدية غير قابلة للتعديل، وتحويلات داخلية.</div>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px">
    <div class="card">
        <div class="kpi-label">Total Operational Cash</div>
        <div class="kpi-value" style="font-size:18px;color:var(--nd-success)">{{ \App\Support\Money::fromMinorUnits($totalOperationalCashMinor)->format() }} د.أ</div>
    </div>
    <div class="card">
        <div class="kpi-label">Today's Inflows</div>
        <div class="kpi-value" style="font-size:18px;color:var(--nd-primary)">{{ \App\Support\Money::fromMinorUnits($todayInflowsMinor)->format() }} د.أ</div>
    </div>
    <div class="card">
        <div class="kpi-label">Today's Outflows</div>
        <div class="kpi-value" style="font-size:18px;color:var(--nd-warning)">{{ \App\Support\Money::fromMinorUnits($todayOutflowsMinor)->format() }} د.أ</div>
    </div>
    <div class="card">
        <div class="kpi-label">Unassigned Events</div>
        <div class="kpi-value" style="font-size:18px;color:var(--nd-danger)">{{ $unassignedCount }}</div>
    </div>
</div>

<div class="grid grid-2">
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="section-head" style="margin-top:0">
                <h2>الحسابات المالية</h2>
                <span class="muted">{{ $accountCards->count() }} حساب</span>
            </div>
            <div class="list">
                @forelse($accountCards as $card)
                    @php($account = $card['account'])
                    <div class="list-row" style="align-items:flex-start">
                        <span class="badge {{ $account->is_active ? 'green' : 'red' }}">{{ $account->is_active ? 'Active' : 'Archived' }}</span>
                        <div class="list-main">
                            <strong>{{ $account->name_ar }} <span class="muted">({{ $account->code }})</span></strong>
                            <small>{{ $account->type }} · {{ $account->currency }}</small>
                            <small>الرصيد {{ \App\Support\Money::fromMinorUnits($card['balance_minor'])->format() }} د.أ · دخول اليوم {{ \App\Support\Money::fromMinorUnits($card['today_inflows_minor'])->format() }} · خروج اليوم {{ \App\Support\Money::fromMinorUnits($card['today_outflows_minor'])->format() }}</small>
                            @can(\App\Support\FinancialPermissions::MANAGE_FINANCIAL_ACCOUNTS)
                                @if($account->is_active)
                                    <form method="POST" action="{{ route('financial-accounts.archive', $account) }}" style="margin-top:8px" onsubmit="return confirm('سيتم أرشفة الحساب مع بقاء السجل التاريخي. هل تريد المتابعة؟')">
                                        @csrf
                                        <button class="btn btn-ghost" type="submit">أرشفة الحساب</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:14px 0">لا توجد حسابات مالية بعد.</div>
                @endforelse
            </div>
        </div>

        @can(\App\Support\FinancialPermissions::MANAGE_FINANCIAL_ACCOUNTS)
            <div class="card form-card" style="margin-bottom:16px">
                <h3 style="margin-top:0">إنشاء حساب مالي</h3>
                <form method="POST" action="{{ route('financial-accounts.store') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>الكود *</label>
                            <input name="code" required placeholder="cash_box">
                        </div>
                        <div class="field">
                            <label>الاسم العربي *</label>
                            <input name="name_ar" required placeholder="صندوق الشركة">
                        </div>
                        <div class="field">
                            <label>النوع *</label>
                            <select name="type" required>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                                <option value="wallet">Wallet</option>
                                <option value="clearing">Clearing</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>الرصيد الافتتاحي</label>
                            <input name="opening_balance" value="0.000">
                        </div>
                        <div class="field">
                            <label>تاريخ الرصيد</label>
                            <input type="datetime-local" name="opening_date" value="{{ now()->format('Y-m-d\\TH:i') }}">
                        </div>
                        <div class="field full">
                            <label>ملاحظات</label>
                            <textarea name="notes"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">إنشاء الحساب</button>
                </form>
            </div>
        @endcan
    </div>

    <div>
        @can(\App\Support\FinancialPermissions::MANAGE_CASH_TRANSFERS)
            <div class="card form-card" style="margin-bottom:16px">
                <h3 style="margin-top:0">تحويل داخلي</h3>
                <form method="POST" action="{{ route('financial-transfers.store') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>من حساب *</label>
                            <select name="from_financial_account_id" required>
                                @foreach($activeAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>إلى حساب *</label>
                            <select name="to_financial_account_id" required>
                                @foreach($activeAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>المبلغ *</label>
                            <input name="amount" required placeholder="10.000">
                        </div>
                        <div class="field">
                            <label>وقت التحويل *</label>
                            <input type="datetime-local" name="transferred_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                        </div>
                        <div class="field">
                            <label>مرجع</label>
                            <input name="reference">
                        </div>
                        <div class="field full">
                            <label>ملاحظات</label>
                            <textarea name="notes"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">تسجيل التحويل</button>
                </form>
            </div>
        @endcan

        <div class="card" style="margin-bottom:16px">
            <div class="section-head" style="margin-top:0">
                <h2>التحويلات الأخيرة</h2>
                <span class="muted">{{ $transfers->count() }}</span>
            </div>
            <div class="list">
                @forelse($transfers as $transfer)
                    <div class="list-row" style="align-items:flex-start">
                        <span class="badge {{ $transfer->reversal ? 'red' : 'blue' }}">{{ $transfer->reversal ? 'Reversed' : 'Transfer' }}</span>
                        <div class="list-main">
                            <strong class="ltr" style="direction:ltr">{{ $transfer->transfer_number }}</strong>
                            <small>{{ optional($transfer->fromAccount)->name_ar }} → {{ optional($transfer->toAccount)->name_ar }} · {{ \App\Support\Money::fromMinorUnits($transfer->amount_minor)->format() }} د.أ</small>
                            <small>{{ $transfer->transferred_at?->format('Y-m-d H:i') }}@if($transfer->reference) · {{ $transfer->reference }} @endif</small>
                            @if($transfer->reversal)
                                <small style="color:var(--nd-danger)">سبب العكس: {{ $transfer->reversal->reason }}</small>
                            @else
                                @can(\App\Support\FinancialPermissions::MANAGE_CASH_TRANSFERS)
                                    <form method="POST" action="{{ route('financial-transfers.reverse', $transfer) }}" style="margin-top:8px" onsubmit="return confirm('سيتم إنشاء حركات عكسية مساوية دون حذف التحويل الأصلي. هل تريد المتابعة؟')">
                                        @csrf
                                        <input name="reason" required placeholder="سبب عكس التحويل" style="max-width:260px">
                                        <button class="btn btn-ghost" type="submit">عكس التحويل</button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:14px 0">لا توجد تحويلات بعد.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>أحداث نقدية غير معينة</h2>
            <span class="muted">{{ $unassignedCount }}</span>
        </div>
        <div class="list">
            @foreach($unassignedPayments as $payment)
                <div class="list-row">
                    <span class="badge gold">Payment</span>
                    <div class="list-main">
                        <strong>{{ \App\Support\Money::fromMinorUnits($payment->amount_minor)->format() }} د.أ</strong>
                        <small>{{ optional($payment->client)->business_name }} · {{ $payment->received_at?->format('Y-m-d H:i') }} · {{ $payment->payment_method }}@if($payment->reference) · {{ $payment->reference }} @endif</small>
                        @can(\App\Support\FinancialPermissions::ASSIGN_HISTORICAL_CASH_ACCOUNTS)
                            <form method="POST" action="{{ route('cash-events.assign-account') }}" style="margin-top:8px">
                                @csrf
                                <input type="hidden" name="event_type" value="payment">
                                <input type="hidden" name="event_id" value="{{ $payment->id }}">
                                <select name="financial_account_id" required>
                                    @foreach($activeAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-ghost" type="submit">تعيين الحساب</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
            @foreach($unassignedRefunds as $refund)
                <div class="list-row">
                    <span class="badge red">Refund</span>
                    <div class="list-main">
                        <strong>{{ \App\Support\Money::fromMinorUnits($refund->amount_minor)->format() }} د.أ</strong>
                        <small>{{ optional($refund->client)->business_name }} · {{ $refund->refunded_at?->format('Y-m-d H:i') }} · {{ $refund->refund_method }}@if($refund->reference) · {{ $refund->reference }} @endif</small>
                        @can(\App\Support\FinancialPermissions::ASSIGN_HISTORICAL_CASH_ACCOUNTS)
                            <form method="POST" action="{{ route('cash-events.assign-account') }}" style="margin-top:8px">
                                @csrf
                                <input type="hidden" name="event_type" value="refund">
                                <input type="hidden" name="event_id" value="{{ $refund->id }}">
                                <select name="financial_account_id" required>
                                    @foreach($activeAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-ghost" type="submit">تعيين الحساب</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
            @if($unassignedCount === 0)
                <div class="muted" style="padding:14px 0">لا توجد أحداث نقدية تاريخية غير معينة.</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>آخر الحركات النقدية</h2>
            <span class="muted">{{ $recentMovements->count() }}</span>
        </div>
        <div class="list">
            @forelse($recentMovements as $movement)
                <div class="list-row">
                    <span class="badge {{ $movement->direction === 'inflow' ? 'green' : 'red' }}">{{ $movement->direction }}</span>
                    <div class="list-main">
                        <strong>{{ \App\Support\Money::fromMinorUnits($movement->amount_minor)->format() }} د.أ · {{ optional($movement->financialAccount)->name_ar }}</strong>
                        <small>{{ $movement->event_type }} · {{ $movement->occurred_at?->format('Y-m-d H:i') }}</small>
                        @if($movement->description)<small class="muted">{{ $movement->description }}</small>@endif
                    </div>
                </div>
            @empty
                <div class="muted" style="padding:14px 0">لا توجد حركات نقدية بعد.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
