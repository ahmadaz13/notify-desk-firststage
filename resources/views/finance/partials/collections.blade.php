{{-- Collections KPIs --}}
<div class="p4-kpis" style="margin-bottom: 20px;">
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">إجمالي الذمم المستحقة (Outstanding)</span>
        <span class="p4-kpi-value is-primary">{{ $outstandingInvoices->count() }}</span>
        <span class="p4-kpi-meta">فواتير قيد التحصيل</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">الذمم المتأخرة (Overdue)</span>
        <span class="p4-kpi-value is-danger">{{ $overdueInvoices->count() }}</span>
        <span class="p4-kpi-meta">تجاوزت تاريخ الاستحقاق</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">مسددة جزئياً (Partially Paid)</span>
        <span class="p4-kpi-value is-warning">{{ $partiallyPaidInvoices->count() }}</span>
        <span class="p4-kpi-meta">تبقى جزء من القيمة</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">أرصدة عملاء دائنة (Credits)</span>
        <span class="p4-kpi-value is-success">{{ $availableCustomerCredits->count() }}</span>
        <span class="p4-kpi-meta">أرصدة قابلة للتخصيص</span>
    </div>
</div>

{{-- Collections Filter Bar --}}
<div class="p4-card" style="margin-bottom: 20px;">
    <form method="GET" action="{{ route('finance.index') }}" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="section" value="collections">
        <div class="p4-field" style="flex: 1; min-width: 180px;">
            <label>العميل</label>
            <select name="client_id" class="p4-select">
                <option value="">جميع العملاء</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected(($filters['client_id'] ?? null) == $c->id)>{{ $c->business_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="p4-field" style="width: 140px;">
            <label>حالة الاستحقاق</label>
            <select name="due_state" class="p4-select">
                <option value="all" @selected(empty($filters['due_state']))>الكل</option>
                <option value="overdue" @selected(($filters['due_state'] ?? null) === 'overdue')>متأخرة</option>
                <option value="not_due" @selected(($filters['due_state'] ?? null) === 'not_due')>غير متأخرة</option>
            </select>
        </div>
        <div class="p4-field" style="width: 140px;">
            <label>حالة السداد</label>
            <select name="settlement_state" class="p4-select">
                <option value="" @selected(empty($filters['settlement_state']))>الكل</option>
                <option value="unpaid" @selected(($filters['settlement_state'] ?? null) === 'unpaid')>غير مسددة</option>
                <option value="partially_paid" @selected(($filters['settlement_state'] ?? null) === 'partially_paid')>مسددة جزئياً</option>
            </select>
        </div>
        <button type="submit" class="p4-btn p4-btn-primary">تطبيق الفلتر</button>
        <a href="{{ route('finance.index', ['section' => 'collections']) }}" class="p4-btn p4-btn-ghost">إعادة تعيين</a>
    </form>
</div>

{{-- Receivables Workspace (Customer & Action First) --}}
<div class="p4-card" style="margin-bottom: 20px;">
    <div class="p4-card-head" style="margin-bottom: 16px;">
        <div>
            <h2 class="p4-card-title" style="font-size: 18px;">مساحة متابعة تحصيل الذمم (Receivables Workspace)</h2>
            <p class="p4-subtitle" style="margin: 2px 0 0 0;">التركيز على العميل، المبلغ المستحق، حالة الاستحقاق، وإجراء التحصيل الفوري.</p>
        </div>
        <span class="p4-badge p4-badge-primary">{{ $outstandingInvoices->count() }} فاتورة مستحقة</span>
    </div>

    @if($outstandingInvoices->isEmpty())
        <div style="text-align: center; padding: 40px 16px; color: #64748B;">
            <div style="font-size: 36px; margin-bottom: 8px;">🎉</div>
            <p style="font-weight: 700; font-size: 16px; margin-bottom: 4px;">لا توجد ذمم مستحقة للتحصيل وفق الفلتر الحالي</p>
            <p style="font-size: 13px;">جميع الفواتير مسددة أو لا تطابق الشروط المحددة.</p>
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 12px;">
            @foreach($outstandingInvoices as $item)
                @php
                    $inv = $item['invoice'];
                    $proj = $item['projection'];
                @endphp
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border: 1px solid #E2E8F0; border-radius: 10px; background: #FFFFFF; flex-wrap: wrap; gap: 12px;">
                    {{-- Customer & Status --}}
                    <div style="display: flex; flex-direction: column; gap: 4px; min-width: 200px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong style="font-size: 15px; color: #0F172A;">{{ $inv->client?->business_name }}</strong>
                            <span class="p4-badge {{ $proj['is_overdue'] ? 'p4-badge-danger' : 'p4-badge-warning' }}">
                                {{ $proj['settlement_status'] }}
                            </span>
                        </div>
                        <div style="font-size: 12px; color: #64748B; display: flex; gap: 12px; flex-wrap: wrap;">
                            <span>فاتورة: <strong class="ltr" style="direction:ltr">{{ $inv->invoice_number }}</strong></span>
                            <span>استحقاق: {{ $inv->due_date?->toDateString() ?: 'غير محدد' }}</span>
                            @if($proj['is_overdue'])
                                <span style="color: #DC2626; font-weight: 600;">متأخرة منذ {{ (int) $inv->due_date?->diffInDays(now()) }} يوم</span>
                            @endif
                        </div>
                    </div>

                    {{-- Amount Due & Quick Action --}}
                    <div style="display: flex; align-items: center; gap: 16px; margin-inline-start: auto;">
                        <div style="text-align: end;">
                            <span style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase; display: block;">المبلغ المتبقي</span>
                            <strong style="font-size: 18px; color: #0055CC;">{{ $proj['outstanding'] }} د.أ</strong>
                            <span style="font-size: 11px; color: #94A3B8; display: block;">من أصل {{ \App\Support\Money::fromMinorUnits($inv->total_minor)->format() }} د.أ</span>
                        </div>

                        <a href="{{ route('clients.show', $inv->client_id) }}" class="p4-btn p4-btn-primary p4-btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                            <span>فتح ملف العميل والتحصيل</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Customer Credits & Unallocated Funds --}}
<x-notify.collapsible-section
    title="أرصدة العملاء والدفعات غير المخصصة (Customer Credits)"
    subtitle="الدفعات الزائدة والإشعارات الدائنة القابلة للتخصيص على فواتير مستقبلية"
    :badge="$availableCustomerCredits->count()"
    :open="false"
>
    @if($availableCustomerCredits->isEmpty())
        <p style="color: #64748B; font-size: 13px; text-align: center; padding: 20px;">لا توجد أرصدة عملاء دائنة غير مخصصة حالياً.</p>
    @else
        <div class="p4-table-wrap">
            <table class="p4-table">
                <thead>
                    <tr>
                        <th>العميل</th>
                        <th>إجمالي الرصيد الدائن المتاح</th>
                        <th>إجراء التخصيص</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($availableCustomerCredits as $credit)
                        <tr>
                            <td><strong>{{ $credit['client']?->business_name }}</strong></td>
                            <td><strong style="color: #10B981;">{{ $credit['available_credit'] }} د.أ</strong></td>
                            <td>
                                <a href="{{ route('clients.show', $credit['client']?->id) }}" class="p4-btn p4-btn-soft p4-btn-sm">
                                    تخصيص في ملف العميل
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-notify.collapsible-section>
