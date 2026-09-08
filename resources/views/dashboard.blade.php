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
                @if(auth()->user()->isAdmin())
                <div class="field">
                    <label>الشريك (اختياري)</label>
                    <select name="partner_id">
                        <option value="">لا يوجد (مباشر)</option>
                        @foreach(\App\Models\Partner::all() as $p)
                            <option value="{{ $p->id }}">{{ $p->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
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

{{-- Partner Earnings Summary Section (Partners Only) --}}
@if(!empty($isPartner) && $isPartner)
<div id="partner-earnings" class="partner-earnings-section" style="margin-top:28px">
    <div class="section-head" style="margin-bottom:14px">
        <div>
            <h2 style="font-size:20px;font-weight:800;color:var(--nd-ink)">ملخص أرباحك</h2>
            <div class="muted" style="margin-top:4px">لوحة الأداء المالي لحصتك من مدفوعات العملاء المحالين</div>
        </div>
        @if($partner && $partner->profit_share_percentage !== null)
            <div>
                <span class="badge" style="background:#ede9fe;color:#5b21b6;font-size:13px;font-weight:700;padding:6px 14px;border-radius:9999px;border:1px solid #ddd6fe">
                    نسبة حصتك: {{ number_format($partner->profit_share_percentage, 1) }}%
                </span>
            </div>
        @endif
    </div>

    <div class="card partner-share-card" style="background:linear-gradient(135deg, #1e1b4b 0%, #2e1065 50%, #3b0764 100%);color:#ffffff;border-radius:16px;padding:24px;border:1px solid rgba(255,255,255,0.15);box-shadow:0 12px 30px -8px rgba(46, 16, 101, 0.35)">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:18px">
            {{-- Sub-item 1: Total Client Payments --}}
            <div style="background:rgba(255, 255, 255, 0.08);padding:18px 20px;border-radius:12px;border:1px solid rgba(255, 255, 255, 0.1);backdrop-filter:blur(10px)">
                <div class="kpi-label" style="color:#c4b5fd;font-weight:600;font-size:13px;margin-bottom:6px">إجمالي مدفوعات عملائك</div>
                <div class="kpi-value" style="font-size:26px;font-weight:800;color:#ffffff;letter-spacing:-0.5px">
                    {{ number_format($totalClientPayments, 2) }} <span style="font-size:15px;font-weight:600;color:#ddd6fe">د.أ</span>
                </div>
                <div style="font-size:12px;color:#a78bfa;margin-top:6px">إجمالي المبالغ المحصلة من عملائك</div>
            </div>

            {{-- Sub-item 2: Net Operating Revenue --}}
            <div style="background:rgba(255, 255, 255, 0.08);padding:18px 20px;border-radius:12px;border:1px solid rgba(255, 255, 255, 0.1);backdrop-filter:blur(10px)">
                <div class="kpi-label" style="color:#c4b5fd;font-weight:600;font-size:13px;margin-bottom:6px">صافي العائد التشغيلي</div>
                <div class="kpi-value" style="font-size:26px;font-weight:800;color:#38bdf8;letter-spacing:-0.5px">
                    {{ number_format($netRevenue, 2) }} <span style="font-size:15px;font-weight:600;color:#bae6fd">د.أ</span>
                </div>
                <div style="font-size:12px;color:#a78bfa;margin-top:6px">بعد استقطاع 20% تكاليف تشغيل النظام</div>
            </div>

            {{-- Sub-item 3: Estimated Share --}}
            <div style="background:rgba(255, 255, 255, 0.14);padding:18px 20px;border-radius:12px;border:1px solid rgba(255, 255, 255, 0.2);backdrop-filter:blur(10px)">
                <div class="kpi-label" style="color:#fbcfe8;font-weight:700;font-size:13px;margin-bottom:6px">حصتك التقديرية</div>
                @if($earnedShare !== null)
                    <div class="kpi-value" style="font-size:28px;font-weight:900;color:#4ade80;letter-spacing:-0.5px">
                        {{ number_format($earnedShare, 2) }} <span style="font-size:16px;font-weight:700;color:#bbf7d0">د.أ</span>
                    </div>
                    <div style="font-size:12px;color:#d1fae5;margin-top:6px">
                        أرباحك التقديرية المكتسبة بنسبة {{ number_format($partner->profit_share_percentage, 1) }}%
                    </div>
                @else
                    <div style="font-size:14px;font-weight:600;color:#fde047;line-height:1.6;padding:6px 0">
                        لم يتم تحديد نسبة الربح بعد، تواصل مع الإدارة
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- Smart Financial Dashboard Section --}}
<div x-data="{ showInvestmentModal: false, showExpenseModal: false }" id="money" style="margin-top:28px">
    <div class="section-head">
        <div>
            <h2 style="font-size:20px;font-weight:800;color:var(--nd-ink)">لوحة المؤشرات المالية الذكية (Financial Dashboard)</h2>
            <div class="muted" style="margin-top:4px">مؤشرات الإيرادات الخام، تكلفة التشغيل، صافي التقييم، ورصيد السيولة</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="button" @click="showInvestmentModal = true" class="btn btn-primary touch-btn" style="min-height:48px;font-size:14px;display:inline-flex;align-items:center;gap:6px">
                <span>＋</span>
                <span>إضافة استثمار</span>
            </button>
            <button type="button" @click="showExpenseModal = true" class="btn btn-ghost touch-btn" style="min-height:48px;font-size:14px;border:1.5px solid var(--nd-accent);color:var(--nd-accent);display:inline-flex;align-items:center;gap:6px">
                <span>＋</span>
                <span>صرف استثماري</span>
            </button>
        </div>
    </div>

    {{-- 5 Financial Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:16px;margin-top:14px">
        {{-- Gross Revenue --}}
        <div class="card" style="border-top:4px solid var(--nd-accent)">
            <div class="kpi-label">إجمالي الإيرادات الخام (Gross Revenue)</div>
            <div class="kpi-value" style="color:var(--nd-ink)">{{ number_format($total_gross_revenue, 2) }} د.أ</div>
            <div class="muted">إجمالي المقبوضات التراكمية المسجلة</div>
        </div>

        {{-- Operating Cost --}}
        <div class="card" style="border-top:4px solid var(--nd-warning)">
            <div class="kpi-label">تكلفة التشغيل (Operating Cost)</div>
            <div class="kpi-value" style="color:var(--nd-warning)">{{ number_format($operational_cost, 2) }} د.أ</div>
            <div class="muted">نسبة التشغيل المعتمدة ({{ $operational_cost_percentage }}%)</div>
        </div>

        {{-- Net Operating Revenue (Highlighted) --}}
        <div class="card financial-card-highlight">
            <div class="kpi-label" style="font-weight:700">صافي الإيراد التشغيلي (Net Operating Revenue)</div>
            <div class="kpi-value">{{ number_format($net_operating_revenue, 2) }} د.أ</div>
            <div class="muted">الإيراد بعد خصم تكلفة التشغيل</div>
        </div>

        {{-- Estimated Market Value --}}
        <div class="card financial-card-gold">
            <div class="kpi-label" style="font-weight:700">القيمة السوقية التقديرية (Estimated Market Value)</div>
            <div class="kpi-value">{{ number_format($estimated_market_value, 2) }} د.أ</div>
            <div class="muted">(بناءً على مضاعف {{ $market_multiplier }}) · ARR × {{ $market_multiplier }}</div>
        </div>

        {{-- Liquidity Balance --}}
        <div class="card" style="border-top:4px solid #3b82f6">
            <div class="kpi-label">رصيد السيولة (Liquidity Balance)</div>
            <div class="kpi-value" style="color:#1d4ed8">{{ number_format($liquidity_balance, 2) }} د.أ</div>
            <div class="muted">الاستثمارات: {{ number_format($total_investments, 2) }} د.أ · الصرف: {{ number_format($total_capital_expenses, 2) }} د.أ</div>
        </div>
    </div>

    {{-- Action Buttons at bottom of financial section --}}
    <div style="display:flex;gap:12px;margin-top:16px;flex-wrap:wrap">
        <button type="button" @click="showInvestmentModal = true" class="btn btn-primary touch-btn" style="min-height:48px;font-size:15px;padding:12px 22px">
            ＋ إضافة استثمار
        </button>
        <button type="button" @click="showExpenseModal = true" class="btn btn-ghost touch-btn" style="min-height:48px;font-size:15px;padding:12px 22px;border:1px solid var(--nd-border)">
            ＋ صرف استثماري
        </button>
    </div>

    {{-- Investment Modal --}}
    <div x-show="showInvestmentModal" x-cloak class="modal-backdrop" @keydown.escape.window="showInvestmentModal = false">
        <div class="modal-box" @click.away="showInvestmentModal = false">
            <div class="modal-header">
                <h3>إضافة استثمار جديد</h3>
                <button type="button" class="modal-close" @click="showInvestmentModal = false">&times;</button>
            </div>
            <form method="POST" action="{{ route('investments.store') }}">
                @csrf
                <div class="field" style="margin-bottom:14px">
                    <label>اسم المستثمر *</label>
                    <input class="touch-input" type="text" name="investor_name" required placeholder="مثال: أحمد عبد الله">
                </div>
                <div class="field" style="margin-bottom:14px">
                    <label>المبلغ (د.أ) *</label>
                    <input class="touch-input" type="number" step="0.01" min="0.01" name="amount" required placeholder="مثال: 5000.00">
                </div>
                <div class="field" style="margin-bottom:14px">
                    <label>تاريخ الإيداع / الدخول *</label>
                    <input class="touch-input" type="date" name="entry_date" required value="{{ now()->toDateString() }}">
                </div>
                <div class="field" style="margin-bottom:20px">
                    <label>ملاحظات (اختياري)</label>
                    <textarea name="notes" placeholder="أي شروط أو تفاصيل إضافية..."></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" class="btn btn-ghost touch-btn" @click="showInvestmentModal = false">إلغاء</button>
                    <button type="submit" class="btn btn-primary touch-btn">حفظ الاستثمار</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Capital Expense Modal --}}
    <div x-show="showExpenseModal" x-cloak class="modal-backdrop" @keydown.escape.window="showExpenseModal = false">
        <div class="modal-box" @click.away="showExpenseModal = false">
            <div class="modal-header">
                <h3>تسجيل صرف استثماري</h3>
                <button type="button" class="modal-close" @click="showExpenseModal = false">&times;</button>
            </div>
            <form method="POST" action="{{ route('capital-expenses.store') }}">
                @csrf
                <div class="field" style="margin-bottom:14px">
                    <label>وصف الصرف *</label>
                    <input class="touch-input" type="text" name="description" required placeholder="مثال: شراء أجهزة وتجهيزات خوادم">
                </div>
                <div class="field" style="margin-bottom:14px">
                    <label>المبلغ (د.أ) *</label>
                    <input class="touch-input" type="number" step="0.01" min="0.01" name="amount" required placeholder="مثال: 750.00">
                </div>
                <div class="field" style="margin-bottom:14px">
                    <label>تاريخ الصرف *</label>
                    <input class="touch-input" type="date" name="expense_date" required value="{{ now()->toDateString() }}">
                </div>
                <div class="field" style="margin-bottom:20px">
                    <label>الاستثمار المرتبط (اختياري)</label>
                    <select class="touch-input" name="investment_id">
                        <option value="">-- بدون ربط باستثمار محدد --</option>
                        @foreach($investments as $inv)
                            <option value="{{ $inv->id }}">{{ $inv->investor_name }} ({{ number_format($inv->amount, 2) }} د.أ - {{ $inv->entry_date }})</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" class="btn btn-ghost touch-btn" @click="showExpenseModal = false">إلغاء</button>
                    <button type="submit" class="btn btn-primary touch-btn">حفظ الصرف</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Monthly Operating Cashflow Summary (Preserved for Ahmad & Khalid) --}}
<div class="section-head" style="margin-top:32px">
    <h2>ملخص السيولة الشهرية (حركة الشهر الحالي)</h2>
    <span class="muted">بيانات التدفق النقدي للشهر الجاري</span>
</div>
<div class="grid grid-3">
    <div class="card">
        <div class="kpi-label">إجمالي المقبوضات الشهرية</div>
        <div class="kpi-value">{{ number_format($monthIncome, 2) }} د.أ</div>
        <div class="muted">دفعات الاشتراكات الفعلية لهذا الشهر</div>
    </div>
    <div class="card">
        <div class="kpi-label">إجمالي المصروفات الشهرية</div>
        <div class="kpi-value">{{ number_format($monthExpenses, 2) }} د.أ</div>
        <div class="muted">مصاريف التشغيل المسجلة لهذا الشهر</div>
    </div>
    <div class="card">
        <div class="kpi-label">الصافي الشهري المحقق</div>
        <div class="kpi-value" style="color:var(--nd-success)">{{ number_format($netCashResult, 2) }} د.أ</div>
        <div class="muted">صافي التدفق النقدي للشهر</div>
    </div>
</div>
@endsection
