@extends('layouts.app')

@section('content')
{{-- Client Header --}}
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.index') }}">العملاء</a> / ملف العميل #{{ $client->id }}</div>
        <h1 class="page-title">{{ $client->business_name }}</h1>
        <div class="muted" style="margin-top:5px">
            {{ $client->business_category }} · {{ $client->city_area }} · <span style="direction:ltr;display:inline-block">{{ $client->phone }}</span>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span class="badge {{ $client->status === 'subscriber' ? 'green' : ($client->status === 'archived' ? '' : 'gold') }}">
            {{ $client->status === 'subscriber' ? 'مشترك نشط' : ($client->status === 'archived' ? 'مؤرشف' : 'فرصة محتملة') }}
        </span>
        @if($client->status === 'prospect')
            <button class="btn btn-primary" onclick="switchTab('subscriptions');document.getElementById('convert-section').scrollIntoView({behavior:'smooth'})">
                ★ تحويل إلى مشترك
            </button>
        @endif
        <a class="btn btn-ghost" href="{{ route('clients.index') }}">← عودة للقائمة</a>
    </div>
</div>

{{-- Top KPIs --}}
<div class="grid grid-3" style="margin-bottom:20px">
    <div class="card">
        <div class="kpi-label">جهة الاتصال والمصدر</div>
        <div class="kpi-value" style="font-size:18px">{{ $client->contact_person ?: 'غير محدد' }}</div>
        <div class="muted">مصدر الفرصة: {{ $client->lead_source }}</div>
    </div>
    <div class="card">
        <div class="kpi-label">المواعيد والمتابعات</div>
        <div class="kpi-value" style="font-size:18px">{{ $appointments->count() }} مواعيد · {{ $followUps->count() }} متابعات</div>
        <div class="muted">{{ $outcomes->count() }} اجتماعات مسجلة النتائج</div>
    </div>
    <div class="card">
        <div class="kpi-label">إجمالي المدفوعات المسجلة</div>
        <div class="kpi-value" style="font-size:18px;color:var(--nd-success)">{{ number_format($payments->sum('amount'), 2) }} د.أ</div>
        <div class="muted">{{ $subscriptions->count() }} اشتراكات · {{ $offers->count() }} عروض</div>
    </div>
</div>

{{-- Navigation Tabs --}}
<div class="card" style="padding:8px 12px;margin-bottom:20px;overflow-x:auto">
    <div style="display:flex;gap:8px;min-width:max-content" id="client-tabs">
        <button class="btn btn-primary tab-btn" onclick="switchTab('overview')" id="tab-btn-overview">
            📋 نظرة عامة والخط الزمني
        </button>
        <button class="btn btn-ghost tab-btn" onclick="switchTab('appointments')" id="tab-btn-appointments">
            📅 المواعيد ({{ $appointments->count() }})
        </button>
        <button class="btn btn-ghost tab-btn" onclick="switchTab('followups')" id="tab-btn-followups">
            📞 المتابعات ({{ $followUps->count() }})
        </button>
        <button class="btn btn-ghost tab-btn" onclick="switchTab('offers')" id="tab-btn-offers">
            💼 العروض التجارية ({{ $offers->count() }})
        </button>
        <button class="btn btn-ghost tab-btn" onclick="switchTab('subscriptions')" id="tab-btn-subscriptions">
            💳 الاشتراكات والمال ({{ $payments->count() }})
        </button>
    </div>
</div>

{{-- TAB 1: OVERVIEW & TIMELINE --}}
<div id="tab-overview" class="tab-pane">
    <div class="grid grid-2">
        {{-- Activity Timeline --}}
        <div class="card">
            <div class="section-head" style="margin-top:0">
                <h2>الخط الزمني الموحد</h2>
                <span class="muted">{{ $timeline->count() }} أحداث مسجلة</span>
            </div>
            <div class="timeline">
                @forelse($timeline as $event)
                    <div class="timeline-item">
                        <span class="timeline-dot" style="{{ str_contains($event->type, 'outcome') ? 'background:var(--nd-warning)' : (str_contains($event->type, 'payment') || str_contains($event->type, 'converted') ? 'background:var(--nd-success)' : '') }}"></span>
                        <strong>{{ $event->description }}</strong>
                        <small>{{ $event->created_at }} · {{ $event->type }}</small>
                    </div>
                @empty
                    <div class="muted" style="padding:20px 0">لا توجد أحداث مسجلة في الخط الزمني بعد.</div>
                @endforelse
            </div>
        </div>

        {{-- Client Details Card --}}
        <div>
            <div class="card" style="margin-bottom:16px">
                <div class="section-head" style="margin-top:0">
                    <h2>بيانات العميل</h2>
                </div>
                <div class="list">
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">اسم النشاط التجاري</small>
                            <strong>{{ $client->business_name }}</strong>
                        </div>
                    </div>
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">رقم الهاتف</small>
                            <strong style="direction:ltr;text-align:right">{{ $client->phone }}</strong>
                        </div>
                    </div>
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">المنطقة والمدينة</small>
                            <strong>{{ $client->city_area }}</strong>
                        </div>
                    </div>
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">فئة النشاط ومصدر العميل</small>
                            <strong>{{ $client->business_category }} · {{ $client->lead_source }}</strong>
                        </div>
                    </div>
                    @if($client->notes)
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">ملاحظات العميل</small>
                            <div>{{ $client->notes }}</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card">
                <div class="section-head" style="margin-top:0">
                    <h2>إجراءات سريعة</h2>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="btn btn-soft" onclick="switchTab('appointments')">＋ جدولة موعد</button>
                    <button class="btn btn-soft" onclick="switchTab('followups')">＋ إضافة متابعة</button>
                    <button class="btn btn-soft" onclick="switchTab('offers')">＋ تقديم عرض</button>
                    <button class="btn btn-soft" onclick="switchTab('subscriptions')">＋ تسجيل دفعة</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- TAB 2: APPOINTMENTS --}}
<div id="tab-appointments" class="tab-pane" style="display:none">
    <div class="grid grid-2">
        {{-- Appointments List --}}
        <div class="card">
            <div class="section-head" style="margin-top:0">
                <h2>سجل المواعيد</h2>
                <span class="muted">{{ $appointments->count() }} مواعيد</span>
            </div>
            <div class="list">
                @forelse($appointments as $appointment)
                    <div class="list-row">
                        <span class="badge {{ $appointment->status === 'completed' ? 'green' : ($appointment->status === 'confirmed' ? 'green' : ($appointment->status === 'cancelled' ? 'red' : 'gold')) }}">
                            {{ $appointment->status }}
                        </span>
                        <div class="list-main">
                            <strong>{{ $appointment->appointment_date }} · {{ $appointment->appointment_time }}</strong>
                            <small>{{ $appointment->appointment_type }} · {{ $appointment->location ?: 'عن بُعد' }}</small>
                            @if($appointment->notes)
                                <small style="color:var(--nd-ink)">{{ $appointment->notes }}</small>
                            @endif
                        </div>
                        <div style="display:flex;gap:6px">
                            @if($appointment->status !== 'completed' && $appointment->status !== 'cancelled')
                                <a class="btn btn-primary" href="{{ route('appointments.outcome.create', $appointment->id) }}">
                                    تسجيل النتيجة
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:20px 0">لا توجد مواعيد مسجلة.</div>
                @endforelse
            </div>
        </div>

        {{-- Schedule New Appointment --}}
        <div class="card form-card">
            <h3 style="margin-top:0">جدولة موعد جديد</h3>
            <form method="POST" action="{{ route('appointments.store') }}">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <div class="form-grid">
                    <div class="field">
                        <label>تاريخ الموعد *</label>
                        <input type="date" name="appointment_date" value="{{ now()->addDay()->toDateString() }}" required>
                    </div>
                    <div class="field">
                        <label>وقت الموعد *</label>
                        <input type="time" name="appointment_time" value="11:00" required>
                    </div>
                    <div class="field">
                        <label>نوع الموعد *</label>
                        <select name="appointment_type" required>
                            <option value="physical_visit">زيارة ميدانية</option>
                            <option value="online_demo">عرض أونلاين</option>
                            <option value="phone_call">مكالمة هاتفية</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>المكان / الرابط</label>
                        <input name="location" placeholder="مقر العميل أو رابط الاجتماع">
                    </div>
                    <div class="field full">
                        <label>ملاحظات الموعد</label>
                        <textarea name="notes" placeholder="هدف الاجتماع والمواضيع المراد مناقشتها"></textarea>
                    </div>
                </div>
                <button class="btn btn-primary" style="margin-top:14px" type="submit">حفظ وجدولة الموعد</button>
            </form>
        </div>
    </div>
</div>

{{-- TAB 3: FOLLOW-UPS --}}
<div id="tab-followups" class="tab-pane" style="display:none">
    <div class="grid grid-2">
        {{-- Follow-ups List --}}
        <div class="card">
            <div class="section-head" style="margin-top:0">
                <h2>سجل المتابعات الدورية</h2>
                <span class="muted">{{ $followUps->count() }} متابعات</span>
            </div>
            <div class="list">
                @forelse($followUps as $fu)
                    <div class="list-row">
                        <span class="badge {{ \Carbon\Carbon::parse($fu->next_follow_up_date)->isPast() ? 'gold' : 'green' }}">
                            {{ $fu->method }}
                        </span>
                        <div class="list-main">
                            <strong>{{ $fu->reason }}</strong>
                            <small style="color:var(--nd-ink)">الخطوة التالية: <b>{{ $fu->next_action }}</b></small>
                            <small class="muted">
                                تاريخ المتابعة القادمة: {{ $fu->next_follow_up_date }}
                                @if($fu->result) · النتيجة: {{ $fu->result }} @endif
                            </small>
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:20px 0">لا توجد متابعات مسجلة لهذا العميل.</div>
                @endforelse
            </div>
        </div>

        {{-- Add Follow-up Form --}}
        <div class="card form-card">
            <h3 style="margin-top:0">تسجيل متابعة جديدة</h3>
            <form method="POST" action="{{ route('clients.follow-ups.store', $client->id) }}">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label>وسيلة المتابعة *</label>
                        <select name="method" required>
                            <option value="phone_call">مكالمة هاتفية</option>
                            <option value="whatsapp">رسالة واتساب</option>
                            <option value="physical_visit">زيارة شخصية</option>
                            <option value="email">بريد إلكتروني</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>سبب / موضوع المتابعة *</label>
                        <input name="reason" required placeholder="متابعة قرار، استفسار عن العرض...">
                    </div>
                    <div class="field">
                        <label>الخطوة التالية المحددة *</label>
                        <input name="next_action" required placeholder="معاودة الاتصال، إرسال تجربة...">
                    </div>
                    <div class="field">
                        <label>تاريخ المتابعة القادم *</label>
                        <input type="date" name="next_follow_up_date" value="{{ now()->addDays(3)->toDateString() }}" required>
                    </div>
                    <div class="field full">
                        <label>نتيجة المتابعة الحالية</label>
                        <textarea name="result" placeholder="ما دار في الاتصال أو المتابعة"></textarea>
                    </div>
                    <div class="field full">
                        <label>ملاحظات إضافية</label>
                        <textarea name="notes" placeholder="ملاحظات للفريق"></textarea>
                    </div>
                </div>
                <button class="btn btn-primary" style="margin-top:14px" type="submit">حفظ المتابعة</button>
            </form>
        </div>
    </div>
</div>

{{-- TAB 4: COMMERCIAL OFFERS --}}
<div id="tab-offers" class="tab-pane" style="display:none">
    <div class="grid grid-2">
        {{-- Offers List --}}
        <div class="card">
            <div class="section-head" style="margin-top:0">
                <h2>العروض التجارية المقدمة</h2>
                <span class="muted">{{ $offers->count() }} عروض</span>
            </div>
            <div class="list">
                @forelse($offers as $offer)
                    <div class="list-row">
                        <span class="badge green">{{ $offer->billing_period }}</span>
                        <div class="list-main">
                            <div style="display:flex;justify-content:space-between;align-items:center">
                                <strong>{{ $offer->package }}</strong>
                                <strong style="color:var(--nd-accent);font-size:16px">{{ number_format($offer->final_agreed_price, 2) }} د.أ</strong>
                            </div>
                            <small class="muted">
                                السعر الأصلي: {{ number_format($offer->price, 2) }} د.أ
                                @if($offer->discount > 0) · الخصم: {{ number_format($offer->discount, 2) }} د.أ @endif
                                · تاريخ العرض: {{ $offer->offer_date }}
                            </small>
                            @if($offer->decision_deadline)
                                <small style="color:var(--nd-warning)">⏰ مهلة القرار حتى: {{ $offer->decision_deadline }}</small>
                            @endif
                            @if($offer->notes)
                                <small style="color:var(--nd-ink)">{{ $offer->notes }}</small>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:20px 0">لا توجد عروض تجارية مسجلة.</div>
                @endforelse
            </div>
        </div>

        {{-- Add Offer Form --}}
        <div class="card form-card">
            <h3 style="margin-top:0">تقديم عرض تجاري جديد</h3>
            <form method="POST" action="{{ route('clients.offers.store', $client->id) }}">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label>اسم الباقة *</label>
                        <input name="package" required placeholder="الباقة الأساسية / باقة المتاجر">
                    </div>
                    <div class="field">
                        <label>دورة الفوترة *</label>
                        <select name="billing_period" required>
                            <option value="monthly">شهري</option>
                            <option value="annual">سنوي</option>
                            <option value="installment">تقسيط</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>السعر الأساسي (د.أ) *</label>
                        <input type="number" step="0.01" name="price" id="offer_price" required placeholder="500" oninput="calculateOfferFinal()">
                    </div>
                    <div class="field">
                        <label>قيمة الخصم (د.أ)</label>
                        <input type="number" step="0.01" name="discount" id="offer_discount" value="0" oninput="calculateOfferFinal()">
                    </div>
                    <div class="field">
                        <label>السعر الصافي المتفق عليه (د.أ)</label>
                        <input type="number" step="0.01" name="final_agreed_price" id="offer_final" placeholder="450">
                    </div>
                    <div class="field">
                        <label>تاريخ العرض *</label>
                        <input type="date" name="offer_date" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="field">
                        <label>مهلة اتخاذ القرار</label>
                        <input type="date" name="decision_deadline" value="{{ now()->addDays(7)->toDateString() }}">
                    </div>
                    <div class="field full">
                        <label>ملاحظات وشروط العرض</label>
                        <textarea name="notes" placeholder="أي شروط خاصة، تدريب، أو ملحقات مجانية"></textarea>
                    </div>
                </div>
                <button class="btn btn-primary" style="margin-top:14px" type="submit">حفظ العرض التجاري</button>
            </form>
        </div>
    </div>
</div>

{{-- TAB 5: SUBSCRIPTIONS & PAYMENTS --}}
<div id="tab-subscriptions" class="tab-pane" style="display:none">
    <div class="grid grid-2">
        {{-- Payments & Schedules --}}
        <div>
            <div class="card" style="margin-bottom:16px">
                <div class="section-head" style="margin-top:0">
                    <h2>الدفعات المسجلة</h2>
                    <span class="muted">{{ $payments->count() }} دفعات</span>
                </div>
                <div class="list">
                    @forelse($payments as $payment)
                        <div class="list-row">
                            <span class="badge green">محصل</span>
                            <div class="list-main">
                                <strong>{{ number_format($payment->amount, 2) }} د.أ</strong>
                                <small class="muted">{{ $payment->paid_at }} · {{ $payment->payment_method }}</small>
                                @if($payment->notes)<small>{{ $payment->notes }}</small>@endif
                            </div>
                        </div>
                    @empty
                        <div class="muted" style="padding:14px 0">لا توجد دفعات محصلة بعد.</div>
                    @endforelse
                </div>
            </div>

            @if(isset($schedules) && $schedules->count() > 0)
            <div class="card">
                <div class="section-head" style="margin-top:0">
                    <h2>جدول الأقساط والاستحقاقات</h2>
                    <span class="muted">{{ $schedules->count() }} أقساط</span>
                </div>
                <div class="list">
                    @foreach($schedules as $schedule)
                        <div class="list-row">
                            <span class="badge {{ $schedule->status === 'paid' ? 'green' : (\Carbon\Carbon::parse($schedule->due_date)->isPast() ? 'red' : 'gold') }}">
                                {{ $schedule->status }}
                            </span>
                            <div class="list-main">
                                <strong>{{ number_format($schedule->amount_due, 2) }} د.أ</strong>
                                <small class="muted">تاريخ الاستحقاق: {{ $schedule->due_date }}</small>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Record Payment or Convert --}}
        <div>
            {{-- Record Payment Form --}}
            <div class="card form-card" style="margin-bottom:16px">
                <h3 style="margin-top:0">تسجيل دفعة جديدة لهذا العميل</h3>
                <form method="POST" action="{{ route('payments.store') }}">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <div class="form-grid">
                        <div class="field">
                            <label>المبلغ المدفوع (د.أ) *</label>
                            <input type="number" step="0.01" name="amount" required placeholder="450">
                        </div>
                        <div class="field">
                            <label>طريقة الدفع *</label>
                            <select name="payment_method" required>
                                <option value="bank_transfer">تحويل بنكي / CliQ</option>
                                <option value="cash">نقدي</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        <div class="field full">
                            <label>تاريخ ووقت التحصيل *</label>
                            <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:14px" type="submit">تسجيل الدفعة</button>
                </form>
            </div>

            {{-- Convert to Subscriber Form --}}
            @if($client->status === 'prospect')
            <div class="card form-card" id="convert-section" style="border:2px solid var(--nd-accent)">
                <h3 style="margin-top:0;color:var(--nd-accent)">تحويل العميل إلى مشترك رسمي</h3>
                <p class="muted">سيتم إنشاء اشتراك نشط وإنشاء جدول الاستحقاقات والأقساط آلياً حسب نوع الفوترة المحدد.</p>
                <form method="POST" action="{{ route('clients.convert', $client->id) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>نوع الفوترة *</label>
                            <select name="billing_type" required>
                                <option value="monthly">شهري (12 قسط شهري)</option>
                                <option value="annual">سنوي (دفعة سنوية كاملة)</option>
                                <option value="installment">تقسيط مخصص</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>السعر الإجمالي للاشتراك (د.أ) *</label>
                            <input type="number" step="0.01" name="total_price" required placeholder="600">
                        </div>
                        <div class="field">
                            <label>تاريخ بداية الاشتراك *</label>
                            <input type="date" name="start_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>عدد الأقساط (في حال التقسيط)</label>
                            <input type="number" name="installments_count" min="2" max="12" value="3">
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:14px;width:100%" type="submit">
                        ★ تأكيد تحويل العميل وتوليد جدول الأقساط
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    document.querySelectorAll('.tab-pane').forEach(function(pane) {
        pane.style.display = 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-ghost');
    });

    var targetPane = document.getElementById('tab-' + tabName);
    var targetBtn = document.getElementById('tab-btn-' + tabName);
    if (targetPane) targetPane.style.display = 'block';
    if (targetBtn) {
        targetBtn.classList.remove('btn-ghost');
        targetBtn.classList.add('btn-primary');
    }
}

function calculateOfferFinal() {
    var p = parseFloat(document.getElementById('offer_price').value) || 0;
    var d = parseFloat(document.getElementById('offer_discount').value) || 0;
    document.getElementById('offer_final').value = Math.max(0, p - d).toFixed(2);
}
</script>
@endsection
