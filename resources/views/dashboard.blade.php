@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">{{ now()->translatedFormat('l، d F Y') }}</div>
        <h1 class="page-title">صباح الخير، {{ auth()->user()->name }}</h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <a class="btn btn-ghost" href="{{ route('clients.import') }}">⬇ استيراد CSV</a>
        <a class="btn btn-primary" href="#quick-add">＋ إضافة سريعة</a>
    </div>
</div>

{{-- Hero Card: Net Cash Result --}}
<div class="hero-card">
    <div class="kpi-label">صافي نتيجة الشهر (المحصل − المصروفات)</div>
    <div class="kpi-value">{{ number_format($netCashResult, 2) }} د.أ</div>
    <div style="color:#d9fff8;font-size:13px;margin-top:8px">
        إجمالي المحصل هذا الشهر: <b>{{ number_format($monthIncome, 2) }} د.أ</b> · إجمالي المصروفات: <b>{{ number_format($monthExpenses, 2) }} د.أ</b>
    </div>
</div>

{{-- Operational & Financial KPIs --}}
<div class="grid grid-4 kpis" style="margin-top:16px">
    <div class="card">
        <div class="kpi-label">تحصيلات اليوم</div>
        <div class="kpi-value" style="color:var(--nd-success)">{{ number_format($todayCollections, 2) }} د.أ</div>
        <div class="muted">دفعات فعلية اليوم</div>
    </div>
    <div class="card">
        <div class="kpi-label">دخل الشهر</div>
        <div class="kpi-value">{{ number_format($monthIncome, 2) }} د.أ</div>
        <div class="muted">كل الاشتراكات والمبيعات</div>
    </div>
    <div class="card">
        <div class="kpi-label">مصروفات الشهر</div>
        <div class="kpi-value">{{ number_format($monthExpenses, 2) }} د.أ</div>
        <div class="muted">التشغيل والتنقلات</div>
    </div>
    <div class="card">
        <div class="kpi-label">متأخرات غير محصلة</div>
        <div class="kpi-value" style="color:var(--nd-danger)">{{ number_format($overdueCollections, 2) }} د.أ</div>
        <div class="muted">جداول دفعات تجاوزت موعدها</div>
    </div>
</div>

{{-- Next Appointment Highlight --}}
<div class="section-head">
    <h2>الموعد القادم</h2>
    <a href="{{ route('clients.index') }}">دليل العملاء ←</a>
</div>
<div class="card">
    @if($nextAppointment)
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
            <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
                    <strong style="font-size:18px">{{ $nextAppointment->business_name }}</strong>
                    <span class="badge {{ $nextAppointment->status === 'confirmed' ? 'green' : 'gold' }}">
                        {{ $nextAppointment->status === 'confirmed' ? 'مؤكد' : 'مجدول' }}
                    </span>
                </div>
                <div class="muted">
                    📅 {{ $nextAppointment->appointment_date }} في تمام الساعة <b>{{ $nextAppointment->appointment_time }}</b> · 📍 {{ $nextAppointment->location ?: 'عن بُعد' }}
                    @if($nextAppointment->phone) · 📞 {{ $nextAppointment->phone }} @endif
                </div>
                @if($nextAppointment->notes)
                    <div style="font-size:12px;color:var(--nd-ink);margin-top:4px">ملاحظات: {{ $nextAppointment->notes }}</div>
                @endif
            </div>
            <div style="display:flex;gap:8px">
                <a class="btn btn-primary" href="{{ route('appointments.outcome.create', $nextAppointment->id) }}">
                    📝 تسجيل النتيجة
                </a>
                <a class="btn btn-soft" href="{{ route('clients.show', $nextAppointment->client_id) }}">
                    فتح ملف العميل
                </a>
            </div>
        </div>
    @else
        <div class="muted" style="padding:10px 0">
            لا يوجد موعد قادم مسجل حالياً. يمكنك جدولة موعد جديد من ملف أي عميل أو من قسم الإضافة السريعة.
        </div>
    @endif
</div>

{{-- Today's Appointments --}}
<div class="section-head">
    <h2>مواعيد اليوم</h2>
    <span class="muted">{{ $appointments->count() }} مواعيد مجدولة</span>
</div>
<div class="card list">
    @forelse($appointments as $appointment)
        <div class="list-row">
            <span class="badge {{ $appointment->status === 'completed' ? 'green' : ($appointment->status === 'confirmed' ? 'green' : ($appointment->status === 'cancelled' ? 'red' : 'gold')) }}">
                {{ $appointment->status === 'completed' ? 'مكتمل' : ($appointment->status === 'confirmed' ? 'مؤكد' : ($appointment->status === 'cancelled' ? 'ملغي' : 'مجدول')) }}
            </span>
            <div class="list-main">
                <strong>{{ $appointment->business_name }}</strong>
                <small>
                    {{ $appointment->appointment_time }} · {{ $appointment->location ?: 'عن بُعد' }}
                    @if($appointment->phone) · {{ $appointment->phone }} @endif
                    @if($appointment->notes) · {{ $appointment->notes }} @endif
                </small>
            </div>
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                @if($appointment->status !== 'completed' && $appointment->status !== 'cancelled')
                    <a class="btn btn-primary" href="{{ route('appointments.outcome.create', $appointment->id) }}">
                        تسجيل النتيجة
                    </a>
                @endif

                @if($appointment->status === 'scheduled')
                    <form method="POST" action="{{ route('appointments.update', $appointment->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="confirmed">
                        <button class="btn btn-soft" type="submit">تأكيد</button>
                    </form>
                @endif

                <a class="btn btn-ghost" href="{{ route('clients.show', $appointment->client_id) }}">
                    الملف
                </a>
            </div>
        </div>
    @empty
        <div class="muted" style="padding:14px 0">لا توجد مواعيد مجدولة لهذا اليوم.</div>
    @endforelse
</div>

{{-- In-App Notifications & Reminders --}}
<div class="section-head">
    <h2>التنبيهات والإشعارات الأخيرة</h2>
    <a href="{{ route('notifications.index') }}">كل الإشعارات ({{ count($unreadNotifications) }} جديدة) ←</a>
</div>
<div class="card list">
    @forelse($unreadNotifications as $notif)
        <div class="list-row" style="background:rgba(204,251,241,0.18);border-radius:12px;padding:12px 14px;margin-bottom:6px">
            <span class="badge {{ str_contains($notif->type, 'overdue') ? 'red' : (str_contains($notif->type, 'appointment') ? 'gold' : 'green') }}">
                @if(str_contains($notif->type, 'appointment')) موعد @elseif(str_contains($notif->type, 'overdue')) متأخرات @else دفعة @endif
            </span>
            <div class="list-main">
                <strong>{{ $notif->title }}</strong>
                <small style="color:var(--nd-ink);margin-top:2px">{{ $notif->message }}</small>
                <small class="muted">{{ $notif->created_at }}</small>
            </div>
            <div style="display:flex;gap:6px">
                @if($notif->action_url)
                    <a class="btn btn-soft" href="{{ $notif->action_url }}">معاينة</a>
                @endif
                <form method="POST" action="{{ route('notifications.read', $notif->id) }}">
                    @csrf
                    <button class="btn btn-ghost" type="submit" title="تعليم كمقروء">✓</button>
                </form>
            </div>
        </div>
    @empty
        <div class="muted" style="padding:14px 0">
            ✓ لا توجد تنبيهات جديدة تتطلب انتباهك.
        </div>
    @endforelse
</div>

{{-- Quick Add Section --}}
<div class="section-head" id="quick-add">
    <h2>إضافة سريعة</h2>
    <span class="muted">أقل من 60 ثانية</span>
</div>
<div class="grid grid-2">
    {{-- Create Prospect --}}
    <div class="card form-card">
        <h3 style="margin-top:0">فرصة جديدة (عميل جديد)</h3>
        <form method="POST" action="{{ route('clients.store') }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>اسم النشاط *</label>
                    <input name="business_name" required placeholder="مقهى الوردة">
                </div>
                <div class="field">
                    <label>الهاتف *</label>
                    <input name="phone" required placeholder="079 000 0000">
                </div>
                <div class="field">
                    <label>المنطقة *</label>
                    <input name="city_area" required placeholder="عبدون">
                </div>
                <div class="field">
                    <label>الفئة *</label>
                    <input name="business_category" required placeholder="مطعم، عيادة...">
                </div>
                <div class="field">
                    <label>المصدر</label>
                    <select name="lead_source">
                        <option>Google Maps</option>
                        <option>Instagram</option>
                        <option>Referral</option>
                        <option>Direct Prospecting</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="field">
                    <label>جهة الاتصال</label>
                    <input name="contact_person" placeholder="الاسم">
                </div>
            </div>
            <button class="btn btn-primary" style="margin-top:15px" type="submit">حفظ العميل</button>
        </form>
    </div>

    {{-- Record Payment --}}
    <div class="card form-card">
        <h3 style="margin-top:0">تسجيل دفعة محصلة</h3>
        <form method="POST" action="{{ route('payments.store') }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>العميل *</label>
                    <select name="client_id" required>
                        @foreach(DB::table('clients')->where('status','!=','archived')->orderBy('business_name')->get() as $client)
                            <option value="{{ $client->id }}">{{ $client->business_name }} ({{ $client->city_area }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>المبلغ (د.أ) *</label>
                    <input type="number" step="0.01" name="amount" required placeholder="450">
                </div>
                <div class="field">
                    <label>طريقة الدفع</label>
                    <select name="payment_method">
                        <option value="bank_transfer">تحويل بنكي / CliQ</option>
                        <option value="cash">نقدي</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                <div class="field">
                    <label>تاريخ ووقت الدفع</label>
                    <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                </div>
            </div>
            <button class="btn btn-primary" style="margin-top:15px" type="submit">تسجيل الدفعة</button>
        </form>
    </div>
</div>

{{-- Financial Breakdown --}}
<div class="section-head" id="money">
    <h2>ملخص المال والسيولة</h2>
    <span class="muted">بيانات الشهر الجاري</span>
</div>
<div class="grid grid-3">
    <div class="card">
        <div class="kpi-label">إجمالي المقبوضات</div>
        <div class="kpi-value">{{ number_format($monthIncome, 2) }} د.أ</div>
        <div class="muted">دفعات الاشتراكات الفعلية</div>
    </div>
    <div class="card">
        <div class="kpi-label">إجمالي المصروفات</div>
        <div class="kpi-value">{{ number_format($monthExpenses, 2) }} د.أ</div>
        <div class="muted">مصاريف التشغيل المسجلة</div>
    </div>
    <div class="card">
        <div class="kpi-label">الصافي المحقق</div>
        <div class="kpi-value" style="color:var(--nd-success)">{{ number_format($netCashResult, 2) }} د.أ</div>
        <div class="muted">صافي التدفق النقدي</div>
    </div>
</div>
@endsection
