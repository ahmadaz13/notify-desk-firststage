@extends('layouts.app')

@section('content')
@php
    $formatWa = function(?string $p) {
        $clean = preg_replace('/[^0-9]/', '', (string)$p);
        if (str_starts_with($clean, '0')) {
            return '962' . substr($clean, 1);
        }
        if (str_starts_with($clean, '7')) {
            return '962' . $clean;
        }
        return $clean;
    };
@endphp

<div x-data="{ mode: '{{ $currentMode }}', setMode(newMode) { this.mode = newMode; const url = new URL(window.location); url.searchParams.set('mode', newMode); window.history.replaceState({}, '', url); } }">
    <div class="page-head">
        <div>
            <div class="eyebrow">{{ now()->translatedFormat('l، d F Y') }}</div>
            <h1 class="page-title">صباح الخير، {{ auth()->user()->name }}</h1>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <a class="btn btn-ghost" href="{{ route('clients.import') }}">⬇ استيراد CSV</a>
            <a class="btn btn-primary" href="#quick-add">＋ إضافة سريعة</a>
        </div>
    </div>

    {{-- Dual-Mode Switcher: اليوم vs المالي --}}
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;padding:6px;background:#f1f5f9;border-radius:14px">
        <div style="display:flex;gap:6px" role="tablist" aria-label="أوضاع لوحة التحكم">
            <a
                href="{{ route('dashboard', ['mode' => 'daily']) }}"
                role="tab"
                :aria-selected="mode === 'daily' ? 'true' : 'false'"
                @click.prevent="setMode('daily')"
                :style="mode === 'daily' ? 'background:#fff;color:var(--nd-primary);box-shadow:0 2px 8px rgba(0,0,0,0.08)' : 'background:transparent;color:#64748b'"
                class="mode-tab-btn"
                style="padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:800;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all 0.15s ease;{{ $currentMode === 'daily' ? 'background:#fff;color:var(--nd-primary);box-shadow:0 2px 8px rgba(0,0,0,0.08);' : 'background:transparent;color:#64748b;' }}"
            >
                <span>⚡</span>
                <span>اليوم (Cockpit)</span>
            </a>
            <a
                href="{{ route('dashboard', ['mode' => 'financial']) }}"
                role="tab"
                :aria-selected="mode === 'financial' ? 'true' : 'false'"
                @click.prevent="setMode('financial')"
                :style="mode === 'financial' ? 'background:#fff;color:var(--nd-primary);box-shadow:0 2px 8px rgba(0,0,0,0.08)' : 'background:transparent;color:#64748b'"
                class="mode-tab-btn"
                style="padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:800;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all 0.15s ease;{{ $currentMode === 'financial' ? 'background:#fff;color:var(--nd-primary);box-shadow:0 2px 8px rgba(0,0,0,0.08);' : 'background:transparent;color:#64748b;' }}"
            >
                <span>📊</span>
                <span>المالي (Financial)</span>
            </a>
        </div>
        <div class="muted" style="font-size:13px;padding-left:12px">
            <span x-show="mode === 'daily'" style="{{ $currentMode === 'financial' ? 'display:none;' : '' }}">وضع العمليات الميدانية السريعة اليومية</span>
            <span x-show="mode === 'financial'" style="{{ $currentMode !== 'financial' ? 'display:none;' : '' }}">لوحة التقييم المالي الذكية والسيولة</span>
        </div>
    </div>

    {{-- ==================== MODE 1: DAILY OPERATIONS COCKPIT ==================== --}}
    <div x-show="mode === 'daily'" class="dashboard-mode-section" style="{{ $currentMode === 'financial' ? 'display:none;' : '' }}" id="daily-mode-section">
        {{-- Quick Actions Row (Under 10 Seconds) --}}
        <div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap">
            <button
                type="button"
                onclick="document.getElementById('quick-expense-fab-btn')?.click()"
                class="btn btn-primary touch-btn"
                style="min-height:48px;font-size:14px;font-weight:800;display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#0d9488,#0f766e)"
            >
                <span>⚡</span>
                <span>＋ مصروف سريع</span>
            </button>
            <a
                href="#quick-add"
                class="btn btn-soft touch-btn"
                style="min-height:48px;font-size:14px;font-weight:700;display:inline-flex;align-items:center;gap:6px"
            >
                <span>📅</span>
                <span>＋ موعد جديد</span>
            </a>
            <a
                href="#quick-add"
                class="btn btn-soft touch-btn"
                style="min-height:48px;font-size:14px;font-weight:700;display:inline-flex;align-items:center;gap:6px"
            >
                <span>👤</span>
                <span>＋ عميل جديد</span>
            </a>
        </div>

        {{-- Today's 5 Snapshot KPI Cards --}}
        <div class="grid grid-5 kpis" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;margin-bottom:20px">
            <div class="card" style="border-top:3px solid var(--nd-primary)">
                <div class="kpi-label">مواعيد اليوم</div>
                <div class="kpi-value" style="color:var(--nd-ink)">{{ $dailySnapshot['appointments_count'] ?? $appointments->count() }}</div>
                <div class="muted">مواعيد مجدولة لفريق العمل</div>
            </div>
            <div class="card" style="border-top:3px solid var(--nd-warning)">
                <div class="kpi-label">متابعات مستحقة</div>
                <div class="kpi-value" style="color:var(--nd-warning)">{{ $dailySnapshot['pending_follow_ups'] ?? 0 }}</div>
                <div class="muted">متابعات تتطلب اتخاذ إجراء</div>
            </div>
            <div class="card" style="border-top:3px solid var(--nd-success)">
                <div class="kpi-label">تحصيلات اليوم</div>
                <div class="kpi-value" style="color:var(--nd-success)">{{ number_format($dailySnapshot['today_collections'] ?? $todayCollections, 2) }} د.أ</div>
                <div class="muted">دفعات فعلية مسجلة</div>
            </div>
            <div class="card" style="border-top:3px solid #ef4444">
                <div class="kpi-label">مصاريف اليوم</div>
                <div class="kpi-value" style="color:#ef4444">{{ number_format($dailySnapshot['today_expenses'] ?? 0, 2) }} د.أ</div>
                <div class="muted">مصاريف تشغيلية ميدانية</div>
            </div>
            <div class="card" style="border-top:3px solid #6366f1;background:#f8fafc">
                <div class="kpi-label">صافي اليوم المحقق</div>
                <div class="kpi-value" style="color:{{ ($dailySnapshot['today_net'] ?? 0) >= 0 ? 'var(--nd-success)' : 'var(--nd-danger)' }}">
                    {{ number_format($dailySnapshot['today_net'] ?? ($todayCollections - ($dailySnapshot['today_expenses'] ?? 0)), 2) }} د.أ
                </div>
                <div class="muted">التحصيلات − مصاريف اليوم</div>
            </div>
        </div>

        {{-- Daily Note Box (Auto-save for Ahmad & Khalid) --}}
        <div
            class="card"
            x-data="{
                noteContent: '{{ addslashes($dailyNote?->content ?? '') }}',
                saveStatus: 'جاهز',
                saveTimeout: null,
                saveNote() {
                    this.saveStatus = 'جارٍ الحفظ...';
                    clearTimeout(this.saveTimeout);
                    this.saveTimeout = setTimeout(async () => {
                        try {
                            const res = await fetch('{{ route('daily-notes.save') }}', {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ content: this.noteContent })
                            });
                            if (res.ok) {
                                this.saveStatus = 'تم الحفظ ✓';
                                setTimeout(() => { this.saveStatus = 'جاهز'; }, 2000);
                            } else {
                                this.saveStatus = 'خطأ في الحفظ!';
                            }
                        } catch (err) {
                            this.saveStatus = 'خطأ في الاتصال!';
                        }
                    }, 800);
                }
            }"
            style="margin-bottom:24px;padding:18px;background:#fdfcfb;border-right:4px solid #f59e0b"
        >
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-size:18px">📝</span>
                    <strong style="font-size:15px;color:var(--nd-ink)">مفكرة الميدان اليومية (أحمد وخالد)</strong>
                </div>
                <span
                    class="badge"
                    :class="saveStatus === 'تم الحفظ ✓' ? 'green' : (saveStatus === 'جارٍ الحفظ...' ? 'gold' : '')"
                    x-text="saveStatus"
                    style="font-size:11px;font-weight:700"
                ></span>
            </div>
            <textarea
                x-model="noteContent"
                @input="saveNote()"
                rows="3"
                placeholder="سجل ملاحظاتك الميدانية السريعة هنا... يتم الحفظ التلقائي فور الكتابة دون الحاجة للضغط على زر حفظ."
                style="width:100%;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0;font-family:inherit;font-size:14px;line-height:1.5;box-sizing:border-box;resize:vertical;background:#fff"
            ></textarea>
        </div>

        {{-- Hero Card: Net Cash Result (Preserved for backward compatibility) --}}
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
                            @if($nextAppointment->phone) · <span style="direction:ltr;display:inline-block">{{ $nextAppointment->phone }}</span> @endif
                        </div>
                        @if($nextAppointment->notes)
                            <div style="font-size:12px;color:var(--nd-ink);margin-top:4px">ملاحظات: {{ $nextAppointment->notes }}</div>
                        @endif
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        @if($nextAppointment->phone)
                            <a href="tel:{{ $nextAppointment->phone }}" class="btn btn-soft touch-btn" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none" title="اتصال">📞</a>
                            <a href="https://wa.me/{{ $formatWa($nextAppointment->phone) }}" target="_blank" class="btn btn-soft touch-btn" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none;background:#dcfce7;color:#15803d;border-color:#bbf7d0" title="واتساب">💬</a>
                        @endif
                        <a class="btn btn-primary touch-btn" href="{{ route('appointments.outcome.create', $nextAppointment->id) }}">
                            📝 تسجيل النتيجة
                        </a>
                        <a class="btn btn-ghost touch-btn" href="{{ route('clients.show', $nextAppointment->client_id) }}">
                            الملف
                        </a>
                    </div>
                </div>
            @else
                <div class="muted" style="padding:10px 0">
                    لا يوجد موعد قادم مسجل حالياً. يمكنك جدولة موعد جديد من ملف أي عميل أو من قسم الإضافة السريعة.
                </div>
            @endif
        </div>

        {{-- Today's Appointments Vertical Timeline --}}
        <div class="section-head">
            <h2>مواعيد اليوم</h2>
            <span class="muted">{{ $todayAppointments->count() }} مواعيد مجدولة</span>
        </div>
        <div class="card list">
            @forelse($todayAppointments as $appointment)
                <div class="list-row" style="padding:12px 14px;align-items:center">
                    <span class="badge {{ $appointment->status === 'completed' ? 'green' : ($appointment->status === 'confirmed' ? 'green' : ($appointment->status === 'cancelled' ? 'red' : 'gold')) }}">
                        {{ $appointment->status === 'completed' ? 'مكتمل' : ($appointment->status === 'confirmed' ? 'مؤكد' : ($appointment->status === 'cancelled' ? 'ملغي' : 'مجدول')) }}
                    </span>
                    <div class="list-main">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <strong>{{ $appointment->client?->business_name ?? $appointment->business_name }}</strong>
                            {{-- Attendee Chips (Ahmad/Khalid) --}}
                            @if(isset($appointment->users) && $appointment->users->isNotEmpty())
                                <div style="display:flex;gap:4px">
                                    @foreach($appointment->users as $attendee)
                                        <span class="badge" style="background:#e0f2fe;color:#0369a1;font-size:11px">👤 {{ $attendee->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <small>
                            ⏰ {{ $appointment->appointment_time }} · 📍 {{ $appointment->location ?: 'عن بُعد' }}
                            @if($appointment->client?->phone ?? $appointment->phone) · <span style="direction:ltr;display:inline-block">{{ $appointment->client?->phone ?? $appointment->phone }}</span> @endif
                            @if($appointment->notes) · {{ $appointment->notes }} @endif
                        </small>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        @php $aptPhone = $appointment->client?->phone ?? $appointment->phone; @endphp
                        @if($aptPhone)
                            <a href="tel:{{ $aptPhone }}" class="btn btn-soft touch-btn" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none" title="اتصال">📞</a>
                            <a href="https://wa.me/{{ $formatWa($aptPhone) }}" target="_blank" class="btn btn-soft touch-btn" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none;background:#dcfce7;color:#15803d;border-color:#bbf7d0" title="واتساب">💬</a>
                        @endif

                        @if($appointment->status !== 'completed' && $appointment->status !== 'cancelled')
                            <a class="btn btn-primary touch-btn" href="{{ route('appointments.outcome.create', $appointment->id) }}">
                                تسجيل النتيجة
                            </a>
                        @endif

                        @if($appointment->status === 'scheduled')
                            <form method="POST" action="{{ route('appointments.update', $appointment->id) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="confirmed">
                                <button class="btn btn-soft touch-btn" type="submit">تأكيد</button>
                            </form>
                        @endif

                        <a class="btn btn-ghost touch-btn" href="{{ route('clients.show', $appointment->client_id) }}">
                            الملف
                        </a>
                    </div>
                </div>
            @empty
                <div style="padding:36px 16px;text-align:center">
                    <div style="font-size:40px;margin-bottom:10px;opacity:0.85">📅</div>
                    <h3 style="margin:0 0 6px 0;font-size:16px;color:var(--nd-ink)">لا توجد مواعيد اليوم</h3>
                    <p class="muted" style="margin:0 0 14px 0;font-size:13px">جدولك الميداني فارغ اليوم. يمكنك جدولة موعد أو زيارة ميدانية جديدة من ملف العميل أو الإضافة السريعة.</p>
                    <a href="#quick-add" class="btn btn-soft touch-btn" style="display:inline-flex;align-items:center;gap:6px;font-size:13px">
                        <span>＋</span>
                        <span>جدولة موعد جديد</span>
                    </a>
                </div>
            @endforelse
        </div>

        {{-- Recent Operational Expenses (Last 5) --}}
        <div class="section-head" style="margin-top:28px">
            <div>
                <h2>أحدث المصاريف التشغيلية (Recent Expenses)</h2>
                <div class="muted">المصاريف الميدانية المسجلة حديثاً من قبل الفريق</div>
            </div>
            <button type="button" onclick="document.getElementById('quick-expense-fab-btn')?.click()" class="btn btn-soft touch-btn" style="display:inline-flex;align-items:center;gap:6px">
                <span>⚡</span>
                <span>＋ إضافة مصروف</span>
            </button>
        </div>
        <div class="card list">
            @forelse($recentExpenses as $exp)
                <div class="list-row" style="padding:12px 14px;align-items:center">
                    <span style="font-size:22px;line-height:1;margin-left:6px">{{ $exp->categoryModel?->icon ?? '📦' }}</span>
                    <div class="list-main">
                        <div style="display:flex;align-items:center;gap:8px">
                            <strong style="color:var(--nd-ink)">{{ $exp->categoryModel?->name ?? $exp->category }}</strong>
                            <span class="badge" style="font-size:11px;background:#f1f5f9">{{ $exp->visibility === 'shared' ? '👥 مشترك' : '🔒 شخصي' }}</span>
                        </div>
                        <small class="muted">
                            {{ $exp->description ?: 'بدون وصف' }} · بواسطة {{ $exp->payer?->name ?? 'غير محدد' }} · {{ $exp->date?->format('Y-m-d') }} {{ $exp->time }}
                        </small>
                    </div>
                    <div style="text-align:left;font-weight:800;font-size:16px;color:#ef4444">
                        {{ number_format($exp->amount, 2) }} د.أ
                    </div>
                </div>
            @empty
                <div style="padding:36px 16px;text-align:center">
                    <div style="font-size:40px;margin-bottom:10px;opacity:0.85">☕</div>
                    <h3 style="margin:0 0 6px 0;font-size:16px;color:var(--nd-ink)">لم تسجل مصاريف اليوم بعد</h3>
                    <p class="muted" style="margin:0 0 14px 0;font-size:13px">سجل أي مصروف ميداني (وقود، ضيافة، تنقلات) بضغطة واحدة في أقل من 10 ثوانٍ عبر الزر العائم FAB.</p>
                    <button type="button" onclick="document.getElementById('quick-expense-fab-btn')?.click()" class="btn btn-primary touch-btn" style="display:inline-flex;align-items:center;gap:6px;font-size:13px">
                        <span>⚡</span>
                        <span>تسجيل مصروف الآن</span>
                    </button>
                </div>
            @endforelse
        </div>

        {{-- Pending Follow-ups Due --}}
        @if(isset($pendingFollowUps) && $pendingFollowUps->isNotEmpty())
        <div class="section-head" style="margin-top:28px">
            <h2>متابعات تتطلب اهتمامك اليوم</h2>
            <span class="muted">{{ $pendingFollowUps->count() }} متابعات مستحقة</span>
        </div>
        <div class="card list">
            @foreach($pendingFollowUps as $fu)
                <div class="list-row" style="padding:12px 14px;align-items:center">
                    <span class="badge {{ \Carbon\Carbon::parse($fu->next_follow_up_date)->isPast() ? 'red' : 'gold' }}">
                        {{ $fu->method }}
                    </span>
                    <div class="list-main">
                        <div style="display:flex;align-items:center;gap:8px">
                            <strong>{{ $fu->business_name }}</strong>
                            <small class="muted">({{ $fu->city_area }})</small>
                        </div>
                        <small style="color:var(--nd-ink)">السبب: {{ $fu->reason }} · الخطوة القادمة: <b>{{ $fu->next_action }}</b></small>
                        <small class="muted">تاريخ الاستحقاق: {{ $fu->next_follow_up_date }}</small>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center">
                        @if($fu->phone)
                            <a href="tel:{{ $fu->phone }}" class="btn btn-soft touch-btn" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none" title="اتصال">📞</a>
                            <a href="https://wa.me/{{ $formatWa($fu->phone) }}" target="_blank" class="btn btn-soft touch-btn" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none;background:#dcfce7;color:#15803d;border-color:#bbf7d0" title="واتساب">💬</a>
                        @endif
                        <a href="{{ route('clients.show', $fu->client_id) }}" class="btn btn-ghost touch-btn">الملف</a>
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        {{-- In-App Notifications & Reminders --}}
        <div class="section-head" style="margin-top:28px">
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
                            <a class="btn btn-soft touch-btn" href="{{ $notif->action_url }}">معاينة</a>
                        @endif
                        <form method="POST" action="{{ route('notifications.read', $notif->id) }}">
                            @csrf
                            <button class="btn btn-ghost touch-btn" type="submit" title="تعليم كمقروء">✓</button>
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
        <div class="section-head" id="quick-add" style="margin-top:28px">
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
                    <button class="btn btn-primary touch-btn" style="margin-top:15px" type="submit">حفظ العميل</button>
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
                                @foreach(DB::table('clients')->where('status','!=','archived')->when(auth()->user()->isPartner(), fn($q) => $q->where('partner_id', auth()->user()->partner_id))->orderBy('business_name')->get() as $client)
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
                                @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                                    <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>تاريخ ووقت الدفع</label>
                            <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                        </div>
                    </div>
                    <button class="btn btn-primary touch-btn" style="margin-top:15px" type="submit">تسجيل الدفعة</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODE 2: SMART FINANCIAL DASHBOARD ==================== --}}
    <div x-show="mode === 'financial'" class="dashboard-mode-section" style="{{ $currentMode !== 'financial' ? 'display:none;' : '' }}" id="financial-mode-section">
        {{-- Partner Earnings Summary Section (Partners Only) --}}
        @if(!empty($isPartner) && $isPartner)
        <div id="partner-earnings" class="partner-earnings-section" style="margin-top:28px">
            <div class="card" style="background:linear-gradient(135deg,#064e3b,#047857);color:#fff;padding:24px;border-radius:18px;box-shadow:0 8px 24px rgba(6,78,59,0.18)">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:18px;border-bottom:1px solid rgba(255,255,255,0.15);padding-bottom:12px">
                    <div>
                        <h2 style="font-size:20px;font-weight:800;margin:0;color:#fff">ملخص أرباحك وحصتك كشريك</h2>
                        <div style="color:#d1fae5;font-size:13px;margin-top:4px">{{ $partner->company_name }}</div>
                    </div>
                    @if($partner->profit_share_percentage)
                        <span class="badge" style="background:#10b981;color:#fff;font-size:13px;padding:6px 14px;border-radius:20px;font-weight:700">
                            نسبتك المتفق عليها: {{ number_format($partner->profit_share_percentage, 1) }}%
                        </span>
                    @endif
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px">
                    <div style="background:rgba(255,255,255,0.08);padding:14px;border-radius:12px;border:1px solid rgba(255,255,255,0.12)">
                        <div style="font-size:13px;color:#d1fae5;margin-bottom:4px">إجمالي مدفوعات عملائك</div>
                        <div style="font-size:24px;font-weight:800;color:#fff">
                            {{ number_format($totalClientPayments, 2) }} <span style="font-size:14px;font-weight:600">د.أ</span>
                        </div>
                        <div style="font-size:12px;color:#a7f3d0;margin-top:4px">إجمالي المحصل من عملائك</div>
                    </div>
                    <div style="background:rgba(255,255,255,0.08);padding:14px;border-radius:12px;border:1px solid rgba(255,255,255,0.12)">
                        <div style="font-size:13px;color:#d1fae5;margin-bottom:4px">صافي العائد التشغيلي</div>
                        <div style="font-size:24px;font-weight:800;color:#fff">
                            {{ number_format($netRevenue, 2) }} <span style="font-size:14px;font-weight:600">د.أ</span>
                        </div>
                        <div style="font-size:12px;color:#a7f3d0;margin-top:4px">بعد خصم نسبة التشغيل</div>
                    </div>
                    <div style="background:rgba(255,255,255,0.14);padding:14px;border-radius:12px;border:1.5px solid rgba(255,255,255,0.25)">
                        <div style="font-size:13px;color:#d1fae5;margin-bottom:4px">حصتك التقديرية</div>
                        @if($earnedShare !== null)
                            <div style="font-size:28px;font-weight:900;color:#6ee7b7">
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
                    <div class="muted" style="margin-top:4px">هذه بطاقات تشغيلية قديمة وليست المصدر المعتمد لـ MRR/ARR أو التقارير المالية. استخدم Executive و Finance.</div>
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

            <div class="card" style="border:1px solid #bfdbfe;background:#eff6ff;margin-bottom:16px">
                <strong>المصدر المعتمد للمؤشرات:</strong>
                <a href="{{ route('executive.index') }}">Executive Dashboard</a>
                <span class="muted">لـ MRR/ARR والحركة الشهرية، و</span>
                <a href="{{ route('finance.index') }}">Finance Reports</a>
                <span class="muted">للقوائم الإدارية، الإيراد المعترف به، النقد والذمم.</span>
            </div>

            {{-- 6 Financial Cards --}}
            <div class="financial-cards-grid">
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

                {{-- Net Operating Revenue --}}
                <div class="card financial-card-highlight">
                    <div class="kpi-label" style="font-weight:700">صافي الإيراد التشغيلي (Net Operating Revenue)</div>
                    <div class="kpi-value">{{ number_format($net_operating_revenue, 2) }} د.أ</div>
                    <div class="muted">الإيراد بعد خصم النسبة التشغيلية ({{ $operational_cost_percentage }}%)</div>
                </div>

                {{-- Net Profit after Actual Operational Expenses --}}
                <div class="card" style="border-top:4px solid var(--nd-primary);background:#f0fdf4">
                    <div class="kpi-label" style="font-weight:700;color:var(--nd-ink)">صافي الربح بعد المصاريف الفعلية</div>
                    <div class="kpi-value" style="color:var(--nd-success)">{{ number_format($net_profit, 2) }} د.أ</div>
                    <div class="muted">بعد خصم المصاريف الفعلية ({{ number_format($all_time_operational_expenses, 2) }} د.أ)</div>
                </div>

                {{-- Estimated Market Value --}}
                <div class="card financial-card-gold">
                    <div class="kpi-label" style="font-weight:700">القيمة السوقية التقديرية (Estimated Market Value)</div>
                    <div class="kpi-value">{{ number_format($estimated_market_value, 2) }} د.أ</div>
                    <div class="muted">بناءً على ARR الفعلي × {{ $market_multiplier }}</div>
                </div>

                {{-- Liquidity Balance --}}
                <div class="card" style="border-top:4px solid #3b82f6">
                    <div class="kpi-label">رصيد السيولة (Liquidity Balance)</div>
                    <div class="kpi-value" style="color:#1d4ed8">{{ number_format($liquidity_balance, 2) }} د.أ</div>
                    <div class="muted">(الاستثمارات + التحصيلات) - (المصاريف الرأسمالية + المصاريف الفعلية)</div>
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
    </div>
</div>
@endsection
