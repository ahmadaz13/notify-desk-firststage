@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">Finance / Collections</div>
        <h1 class="page-title">التحصيل والذمم المدينة</h1>
        <div class="muted" style="margin-top:5px">فواتير V2 الصادرة، الأرصدة المستحقة، والمدفوعات غير المخصصة.</div>
    </div>
</div>

<div class="card" style="margin-bottom:16px">
    <form method="GET" action="{{ route('collections.index') }}" class="form-grid">
        <div class="field">
            <label>العميل</label>
            <select name="client_id">
                <option value="">كل العملاء</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected(($filters['client_id'] ?? null) == $client->id)>{{ $client->business_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>حالة الاستحقاق</label>
            <select name="due_state">
                <option value="all" @selected(empty($filters['due_state']))>الكل</option>
                <option value="overdue" @selected(($filters['due_state'] ?? null) === 'overdue')>متأخر</option>
                <option value="not_due" @selected(($filters['due_state'] ?? null) === 'not_due')>غير مستحق بعد</option>
            </select>
        </div>
        <div class="field">
            <label>حالة السداد</label>
            <select name="settlement_state">
                <option value="">الكل</option>
                <option value="unpaid" @selected(($filters['settlement_state'] ?? null) === 'unpaid')>غير مدفوع</option>
                <option value="partially_paid" @selected(($filters['settlement_state'] ?? null) === 'partially_paid')>مدفوع جزئياً</option>
                <option value="paid" @selected(($filters['settlement_state'] ?? null) === 'paid')>مدفوع</option>
            </select>
        </div>
        <div class="field">
            <label>من تاريخ استحقاق</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="field">
            <label>إلى تاريخ استحقاق</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="field" style="align-self:end">
            <button class="btn btn-primary" type="submit">تصفية</button>
        </div>
    </form>
</div>

<div class="grid grid-4" style="margin-bottom:16px">
    <div class="card">
        <div class="kpi-label">All Outstanding</div>
        <div class="kpi-value" style="font-size:18px">{{ $outstandingInvoices->count() }}</div>
    </div>
    <div class="card">
        <div class="kpi-label">Overdue</div>
        <div class="kpi-value" style="font-size:18px;color:var(--nd-danger)">{{ $overdueInvoices->count() }}</div>
    </div>
    <div class="card">
        <div class="kpi-label">Partially Paid</div>
        <div class="kpi-value" style="font-size:18px;color:var(--nd-warning)">{{ $partiallyPaidInvoices->count() }}</div>
    </div>
    <div class="card">
        <div class="kpi-label">Customer Credits</div>
        <div class="kpi-value" style="font-size:18px;color:var(--nd-success)">{{ $availableCustomerCredits->count() }}</div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>الفواتير المستحقة</h2>
            <span class="muted">{{ $outstandingInvoices->count() }} فاتورة</span>
        </div>
        <div class="list">
            @forelse($outstandingInvoices as $item)
                @php($invoice = $item['invoice'])
                @php($projection = $item['projection'])
                <div class="list-row" style="align-items:flex-start">
                    <span class="badge {{ $projection['is_overdue'] ? 'red' : 'gold' }}">{{ $projection['settlement_status'] }}</span>
                    <div class="list-main">
                        <strong class="ltr" style="direction:ltr">{{ $invoice->invoice_number }}</strong>
                        <small class="muted">{{ $invoice->client->business_name }} · استحقاق {{ optional($invoice->due_date)->format('Y-m-d') }}</small>
                        <small>الإجمالي {{ \App\Support\Money::fromMinorUnits($invoice->total_minor)->format() }} د.أ · دفعات {{ $projection['allocated'] }} د.أ · رصيد دائن {{ $projection['credit_applied'] }} د.أ · مستحق {{ $projection['outstanding'] }} د.أ</small>
                        <a class="btn btn-ghost" style="margin-top:8px" href="{{ route('clients.show', $invoice->client_id) }}">فتح ملف العميل</a>
                    </div>
                </div>
            @empty
                <div class="muted" style="padding:14px 0">لا توجد فواتير مستحقة ضمن التصفية الحالية.</div>
            @endforelse
        </div>
    </div>

    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="section-head" style="margin-top:0">
                <h2>الفواتير المتأخرة</h2>
                <span class="muted">{{ $overdueInvoices->count() }}</span>
            </div>
            <div class="list">
                @forelse($overdueInvoices as $item)
                    @php($invoice = $item['invoice'])
                    @php($projection = $item['projection'])
                    <div class="list-row">
                        <span class="badge red">{{ $projection['age_bucket'] }}</span>
                        <div class="list-main">
                            <strong class="ltr" style="direction:ltr">{{ $invoice->invoice_number }}</strong>
                            <small>{{ $invoice->client->business_name }} · {{ $projection['outstanding'] }} د.أ</small>
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:14px 0">لا توجد فواتير متأخرة.</div>
                @endforelse
            </div>
        </div>

        <div class="card" style="margin-bottom:16px">
            <div class="section-head" style="margin-top:0">
                <h2>مدفوع جزئياً</h2>
                <span class="muted">{{ $partiallyPaidInvoices->count() }}</span>
            </div>
            <div class="list">
                @forelse($partiallyPaidInvoices as $item)
                    @php($invoice = $item['invoice'])
                    @php($projection = $item['projection'])
                    <div class="list-row">
                        <span class="badge gold">partial</span>
                        <div class="list-main">
                            <strong class="ltr" style="direction:ltr">{{ $invoice->invoice_number }}</strong>
                            <small>{{ $invoice->client->business_name }} · دفعات {{ $projection['allocated'] }} د.أ · رصيد دائن {{ $projection['credit_applied'] }} د.أ · متبقٍ {{ $projection['outstanding'] }} د.أ</small>
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:14px 0">لا توجد فواتير مدفوعة جزئياً.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="section-head" style="margin-top:0">
                <h2>أرصدة العملاء المتاحة</h2>
                <span class="muted">{{ $availableCustomerCredits->count() }}</span>
            </div>
            <div class="list">
                @forelse($availableCustomerCredits as $item)
                    @php($source = $item['source'])
                    @php($projection = $item['projection'])
                    <div class="list-row">
                        <span class="badge green">{{ $item['source_type'] === 'payment' ? 'payment credit' : 'credit note' }}</span>
                        <div class="list-main">
                            <strong>{{ $item['available'] }} د.أ</strong>
                            @if($item['source_type'] === 'payment')
                                <small>{{ $source->client->business_name }} · دفعة {{ $source->received_at?->format('Y-m-d H:i') }} · {{ $source->payment_method }}</small>
                            @else
                                <small>{{ $source->client->business_name }} · إشعار دائن {{ $source->credit_note_number }} · {{ $source->issue_date?->format('Y-m-d') }}</small>
                            @endif
                            <a class="btn btn-ghost" style="margin-top:8px" href="{{ route('clients.show', $source->client_id) }}">فتح ملف العميل</a>
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:14px 0">لا توجد أرصدة عملاء متاحة.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
