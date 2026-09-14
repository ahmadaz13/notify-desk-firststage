@extends('layouts.partner')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">{{ now()->translatedFormat('l، d F Y') }}</div>
        <h1 class="page-title">صباح الخير، {{ $partner->company_name }}</h1>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <a class="btn btn-ghost touch-btn" href="{{ route('clients.index') }}">دليل عملاؤنا ←</a>
    </div>
</div>

@if(session('is_first_login'))
<div class="card" style="background:linear-gradient(135deg,#0C86ED,#0284c7);color:#fff;border-radius:16px;padding:22px;margin-bottom:24px;box-shadow:0 10px 25px rgba(12,134,237,0.25)" x-data="{ dismissed: false }" x-show="!dismissed">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="font-size:24px">🎉</span>
                <h2 style="margin:0;font-size:18px;font-weight:800;color:#fff">أهلاً بك في بوابة الشركاء الرسمية · Notify</h2>
            </div>
            <p style="margin:0 0 10px 0;font-size:14px;color:#e0f2fe;line-height:1.6">
                تم تفعيل حسابك بنجاح. هذه مساحتك الآمنة والمعزولة لمتابعة العملاء المحالين، استلام طلبات مناديب المبيعات، ومراقبة الحصص والأرباح المكتسبة بكل شفافية.
            </p>
            <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:13px;color:#f0f9ff;font-weight:600">
                <span>✓ عزل بيانات كامل ومشفر</span>
                <span>✓ رابط مندوب خاص بشركتكم</span>
                <span>✓ احتساب فوري للحصص والأرباح</span>
            </div>
        </div>
        <button type="button" @click="dismissed = true" style="background:rgba(255,255,255,0.2);border:none;color:#fff;border-radius:8px;padding:6px 12px;font-size:13px;font-weight:700;cursor:pointer">
            إغلاق ✕
        </button>
    </div>
</div>
@endif

{{-- Delegate Link Card --}}
<div class="card" style="background:#f0fdf4;border:1px solid #86efac;margin-bottom:24px;padding:18px" x-data="{ copied: false }">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
        <div style="font-weight:800;color:#166534;font-size:15px">🔗 رابط المندوب الخاص بشركتكم لاستقبال العملاء:</div>
        <span class="badge green">مفعّل وجاهز</span>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="text" readonly value="{{ $delegateUrl }}" class="touch-input" style="flex:1;background:#fff;direction:ltr;font-family:monospace;font-size:13px" id="partner-delegate-link">
        <button type="button" class="btn btn-primary touch-btn" @click="navigator.clipboard.writeText('{{ $delegateUrl }}'); copied = true; setTimeout(() => copied = false, 2500)">
            <span x-show="!copied">نسخ الرابط</span>
            <span x-show="copied" style="display:none">تم النسخ! ✓</span>
        </button>
        <a href="{{ $delegateUrl }}" target="_blank" class="btn btn-soft touch-btn">معاينة النموذج ↗</a>
    </div>
    <small class="muted" style="display:block;margin-top:6px;color:#15803d">شارك هذا الرابط مع مناديبك لإدخال العملاء الجدد مباشرة وربطهم بحسابكم.</small>
</div>

{{-- Partner Earnings Section --}}
<div id="partner-earnings" class="partner-earnings-section" style="margin-bottom:28px">
    <div class="section-head" style="margin-bottom:14px">
        <div>
            <h2 style="font-size:20px;font-weight:800;color:var(--nd-ink)">ملخص أرباحك</h2>
            <div class="muted" style="margin-top:4px">لوحة الأداء المالي لحصتك من مدفوعات العملاء المحالين عبر شركتكم</div>
        </div>
        @if($partner->profit_share_percentage !== null)
            <div>
                <span class="badge" style="background:#ede9fe;color:#5b21b6;font-size:13px;font-weight:700;padding:6px 14px;border-radius:9999px;border:1px solid #ddd6fe">
                    نسبة حصتك: {{ number_format($partner->profit_share_percentage, 1) }}%
                </span>
            </div>
        @endif
    </div>

    <div class="card partner-share-card" style="background:linear-gradient(135deg, #1e1b4b 0%, #2e1065 50%, #3b0764 100%);color:#ffffff;border-radius:16px;padding:24px;border:1px solid rgba(255,255,255,0.15);box-shadow:0 12px 30px -8px rgba(46, 16, 101, 0.35)">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:18px">
            {{-- Item 1: Total Client Payments --}}
            <div style="background:rgba(255, 255, 255, 0.08);padding:18px 20px;border-radius:12px;border:1px solid rgba(255, 255, 255, 0.1);backdrop-filter:blur(10px)">
                <div class="kpi-label" style="color:#c4b5fd;font-weight:600;font-size:13px;margin-bottom:6px">إجمالي مدفوعات عملائك</div>
                <div class="kpi-value" style="font-size:26px;font-weight:800;color:#ffffff;letter-spacing:-0.5px">
                    {{ number_format($totalClientPayments, 2) }} <span style="font-size:15px;font-weight:600;color:#ddd6fe">د.أ</span>
                </div>
                <div style="font-size:12px;color:#a78bfa;margin-top:6px">إجمالي المبالغ المحصلة من عملائك</div>
            </div>

            {{-- Item 2: Net Operating Revenue --}}
            <div style="background:rgba(255, 255, 255, 0.08);padding:18px 20px;border-radius:12px;border:1px solid rgba(255, 255, 255, 0.1);backdrop-filter:blur(10px)">
                <div class="kpi-label" style="color:#c4b5fd;font-weight:600;font-size:13px;margin-bottom:6px">صافي العائد التشغيلي</div>
                <div class="kpi-value" style="font-size:26px;font-weight:800;color:#38bdf8;letter-spacing:-0.5px">
                    {{ number_format($netOperatingRevenue, 2) }} <span style="font-size:15px;font-weight:600;color:#bae6fd">د.أ</span>
                </div>
                <div style="font-size:12px;color:#a78bfa;margin-top:6px">بعد استقطاع {{ number_format($deductionPercentage, 1) }}% تكاليف تشغيل النظام</div>
            </div>

            {{-- Item 3: Estimated Share --}}
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

{{-- Partner Daily Snapshot --}}
<div class="section-head">
    <h2>نشاط اليوم الخاص بشركتكم</h2>
    <span class="muted">متابعة مواعيد وعمليات عملاؤكم</span>
</div>
<div class="grid grid-3" style="margin-bottom:24px">
    <div class="card">
        <div class="kpi-label">مواعيد اليوم</div>
        <div class="kpi-value">{{ $appointments->count() }}</div>
        <div class="muted">مواعيد مجدولة لعملائكم اليوم</div>
    </div>
    <div class="card">
        <div class="kpi-label">متابعات قادمة</div>
        <div class="kpi-value">{{ $followUps->count() }}</div>
        <div class="muted">متابعات مستحقة خلال الأيام القادمة</div>
    </div>
    <div class="card">
        <div class="kpi-label">تحصيلات اليوم</div>
        <div class="kpi-value" style="color:var(--nd-success)">{{ number_format($todayCollections, 2) }} د.أ</div>
        <div class="muted">دفعات تم تحصيلها من عملائكم اليوم</div>
    </div>
</div>

{{-- Today's Appointments List --}}
@if($appointments->isNotEmpty())
<div class="section-head">
    <h2>مواعيد عملاؤكم اليوم</h2>
    <span class="muted">{{ $appointments->count() }} مواعيد</span>
</div>
<div class="card list" style="margin-bottom:28px">
    @foreach($appointments as $appointment)
        <div class="list-row">
            <span class="badge {{ $appointment->status === 'completed' ? 'green' : ($appointment->status === 'confirmed' ? 'green' : ($appointment->status === 'cancelled' ? 'red' : 'gold')) }}">
                {{ $appointment->status === 'completed' ? 'مكتمل' : ($appointment->status === 'confirmed' ? 'مؤكد' : ($appointment->status === 'cancelled' ? 'ملغي' : 'مجدول')) }}
            </span>
            <div class="list-main">
                <strong>{{ $appointment->business_name }}</strong>
                <small>
                    {{ $appointment->appointment_time }} · {{ $appointment->location ?: 'عن بُعد' }}
                    @if($appointment->phone) · {{ $appointment->phone }} @endif
                </small>
            </div>
            <a class="btn btn-soft touch-btn" href="{{ route('clients.show', $appointment->client_id) }}">عرض الملف</a>
        </div>
    @endforeach
</div>
@endif

{{-- Partner's Clients Directory --}}
<div class="section-head">
    <div>
        <h2 style="font-size:18px;font-weight:800;color:var(--nd-ink)">عملاؤكم المسجلون</h2>
        <div class="muted" style="margin-top:2px">قائمة العملاء التابعين لشركتكم عبر الرابط أو الإحالة ({{ $clients->total() }} عميل)</div>
    </div>
</div>

<div class="card" style="margin-bottom:16px;padding:14px">
    <form method="GET" action="{{ route('partner.dashboard') }}" style="display:flex;gap:10px;flex-wrap:wrap">
        <input type="text" name="q" value="{{ $query }}" placeholder="بحث بالاسم أو الهاتف أو المنطقة..." class="touch-input" style="flex:1;min-width:200px">
        <select name="status" class="touch-input" style="min-width:140px">
            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>جميع الحالات</option>
            <option value="prospect" {{ $status === 'prospect' ? 'selected' : '' }}>فرصة محتملة</option>
            <option value="subscriber" {{ $status === 'subscriber' ? 'selected' : '' }}>مشترك نشط</option>
        </select>
        <button type="submit" class="btn btn-primary touch-btn">بحث</button>
        @if($query || $status !== 'all')
            <a href="{{ route('partner.dashboard') }}" class="btn btn-ghost touch-btn">إعادة تعيين</a>
        @endif
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;text-align:right">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid var(--nd-border);font-size:13px;color:var(--nd-muted)">
                    <th style="padding:14px">اسم المنشأة</th>
                    <th style="padding:14px">الهاتف</th>
                    <th style="padding:14px">المنطقة</th>
                    <th style="padding:14px">الفئة</th>
                    <th style="padding:14px">الحالة</th>
                    <th style="padding:14px;text-align:center">الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $c)
                    <tr style="border-bottom:1px solid var(--nd-border);font-size:14px">
                        <td style="padding:14px;font-weight:700">{{ $c->business_name }}</td>
                        <td style="padding:14px;direction:ltr;text-align:right;font-family:monospace">{{ $c->phone }}</td>
                        <td style="padding:14px">{{ $c->city_area }}</td>
                        <td style="padding:14px">{{ $c->business_category }}</td>
                        <td style="padding:14px">
                            <span class="badge {{ $c->status === 'subscriber' ? 'green' : 'gold' }}">
                                {{ $c->status === 'subscriber' ? 'مشترك' : 'فرصة' }}
                            </span>
                        </td>
                        <td style="padding:14px;text-align:center">
                            <a href="{{ route('clients.show', $c->id) }}" class="btn btn-soft" style="padding:6px 12px">عرض الملف</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:32px;text-align:center" class="muted">
                            لا يوجد عملاء مسجلين حالياً تابعين لشركتكم.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($clients->hasPages())
    <div style="margin-top:16px">
        {{ $clients->links() }}
    </div>
@endif
@endsection
