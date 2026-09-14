@extends('layouts.app')

@section('content')
@php
    $currentStage = \App\Support\ClientLifecycle::normalizeStage($client->stage ?? null, $client->status ?? null);
    $currentStageLabel = $lifecycleLabels[$currentStage] ?? $currentStage;
    $stageBadgeClass = $currentStage === \App\Support\ClientLifecycle::SUBSCRIBER ? 'green' : ($currentStage === \App\Support\ClientLifecycle::CLOSED ? '' : 'gold');
    $primaryContact = $client->primaryContact;
    $contactPhone = optional($primaryContact)->primary_phone ?: $client->phone;
    $contactName = optional($primaryContact)->name ?: $client->contact_person;
    $businessPhone = $client->business_phone ?: $client->phone;
@endphp
{{-- Client Header --}}
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.index') }}">العملاء</a> / ملف العميل #{{ $client->id }}</div>
        <h1 class="page-title">{{ $client->business_name }}</h1>
        <div class="muted" style="margin-top:5px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span>{{ $client->business_type ?: $client->business_category }} · {{ $client->city ?: $client->city_area }}@if($client->area) / {{ $client->area }} @endif · <span style="direction:ltr;display:inline-block">{{ $businessPhone }}</span></span>
            @php
                $cleanPhone = preg_replace('/[^0-9]/', '', optional($primaryContact)->whatsapp_number ?: $contactPhone);
                $waPhone = str_starts_with($cleanPhone, '0') ? '962' . substr($cleanPhone, 1) : (str_starts_with($cleanPhone, '7') ? '962' . $cleanPhone : $cleanPhone);
            @endphp
            <a href="tel:{{ $contactPhone }}" class="btn btn-soft" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:12px;font-size:18px;text-decoration:none" title="اتصال هاتفي">📞</a>
            <a href="https://wa.me/{{ $waPhone }}" target="_blank" class="btn btn-soft" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:12px;font-size:18px;text-decoration:none;background:#dcfce7;color:#15803d;border-color:#bbf7d0" title="واتساب">💬</a>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span class="badge {{ $stageBadgeClass }}">
            {{ $currentStageLabel }}
        </span>
        @if($currentStage !== \App\Support\ClientLifecycle::SUBSCRIBER && $currentStage !== \App\Support\ClientLifecycle::CLOSED)
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
        <div class="kpi-value" style="font-size:18px">{{ $contactName ?: 'غير محدد' }}</div>
        <div class="muted">مصدر الفرصة: {{ $client->lead_source }}@if($client->source_reference) · {{ $client->source_reference }} @endif</div>
    </div>
    <div class="card">
        <div class="kpi-label">المواعيد والمتابعات</div>
        <div class="kpi-value" style="font-size:18px">{{ $appointments->count() }} مواعيد · {{ $followUps->count() }} متابعات</div>
        <div class="muted">{{ $outcomes->count() }} اجتماعات مسجلة النتائج · {{ $installations->count() }} تركيبات</div>
    </div>
    <div class="card">
        <div class="kpi-label">إجمالي المدفوعات والاشتراكات</div>
        <div class="kpi-value" style="font-size:18px;color:var(--nd-success)">{{ number_format($payments->sum('amount'), 2) }} د.أ</div>
        <div class="muted">{{ $subscriptions->count() }} اشتراكات · {{ $contracts->count() }} عقود · {{ $offers->count() }} عروض</div>
    </div>
</div>

<style>
@media (min-width: 769px) {
    .mobile-accordion-header {
        display: none !important;
    }
    .desktop-tabs-card {
        display: block !important;
    }
}
@media (max-width: 768px) {
    .desktop-tabs-card {
        display: none !important;
    }
    .mobile-accordion-header {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        background: #fff;
        border: 1px solid var(--nd-border, #e2e8f0);
        border-radius: 12px;
        margin-bottom: 10px;
        cursor: pointer;
        font-weight: 700;
        font-size: 15px;
        color: var(--nd-ink, #0f172a);
        user-select: none;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .mobile-accordion-header:hover {
        background: #f8fafc;
    }
    .mobile-accordion-header.active {
        background: #f0fdfa;
        border-color: var(--nd-primary, #0f766e);
        color: var(--nd-primary, #0f766e);
        margin-bottom: 0;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
    }
    .tab-pane {
        margin-bottom: 14px;
    }
    .mobile-accordion-header.active + .tab-pane {
        background: #fff;
        border: 1px solid var(--nd-primary, #0f766e);
        border-top: none;
        border-bottom-left-radius: 12px;
        border-bottom-right-radius: 12px;
        padding: 14px 12px;
    }
}
</style>

{{-- Navigation Tabs (Desktop) --}}
<div class="card desktop-tabs-card" style="padding:8px 12px;margin-bottom:20px;overflow-x:auto">
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
            💳 الاشتراكات والعقود ({{ $subscriptions->count() }})
        </button>
    </div>
</div>

{{-- SECTION 1: OVERVIEW & TIMELINE --}}
<div class="mobile-accordion-header active" onclick="toggleAccordion('overview')" id="acc-header-overview">
    <div style="display:flex;align-items:center;gap:8px">
        <span>📋</span>
        <span>نظرة عامة والخط الزمني</span>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <span class="badge" style="font-size:11px">{{ $timeline->count() }} أحداث</span>
        <span class="chevron-icon" style="transition:transform 0.2s ease;display:inline-block;transform:rotate(180deg)">▼</span>
    </div>
</div>
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
                    <h2>مرحلة العميل</h2>
                    <span class="badge {{ $stageBadgeClass }}">{{ $currentStageLabel }}</span>
                </div>
                <form method="POST" action="{{ route('clients.stage.update', $client->id) }}">
                    @csrf
                    @method('PATCH')
                    <div class="form-grid">
                        <div class="field">
                            <label>المرحلة الحالية</label>
                            <select name="stage" required>
                                @foreach($lifecycleStages as $stageOption)
                                    <option value="{{ $stageOption }}" @selected($currentStage === $stageOption)>{{ $lifecycleLabels[$stageOption] ?? $stageOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>سبب الإغلاق</label>
                            <input name="closed_reason" value="{{ old('closed_reason', $client->closed_reason) }}" placeholder="يستخدم عند اختيار مغلق">
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">تحديث المرحلة</button>
                </form>
            </div>

            <div class="card" style="margin-bottom:16px">
                <div class="section-head" style="margin-top:0">
                    <h2>التركيب المجاني</h2>
                    <span class="muted">{{ $activeInstallationAppointments->count() }} مواعيد نشطة · {{ $installations->count() }} منجزة</span>
                </div>
                <form method="POST" action="{{ route('clients.installations.schedule', $client->id) }}" style="margin-bottom:16px">
                    @csrf
                    <h3 style="margin:0 0 10px 0;font-size:16px">جدولة تركيب مجاني</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label>تاريخ التركيب *</label>
                            <input type="date" name="appointment_date" value="{{ now()->addDay()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>وقت التركيب *</label>
                            <input type="time" name="appointment_time" value="11:00" required>
                        </div>
                        <div class="field">
                            <label>الفرع</label>
                            <input name="branch_name" placeholder="الفرع الرئيسي">
                        </div>
                        <div class="field">
                            <label>الموقع</label>
                            <input name="location" value="{{ $client->location_text }}" placeholder="موقع التركيب">
                        </div>
                        <div class="field full">
                            <label style="font-weight:700">الفريق المكلف</label>
                            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px">
                                @foreach($teamUsers as $u)
                                    <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                                        <input type="checkbox" name="attendees[]" value="{{ $u->id }}" {{ $u->id === auth()->id() ? 'checked' : '' }} style="width:16px;height:16px;accent-color:var(--nd-primary)">
                                        <span>{{ $u->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="field full">
                            <label>ملاحظات</label>
                            <textarea name="notes" placeholder="أي تجهيزات أو تفاصيل موقع مهمة"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">جدولة التركيب</button>
                </form>

                <form method="POST" action="{{ route('clients.installations.complete', $client->id) }}" style="border-top:1px solid var(--nd-border);padding-top:16px">
                    @csrf
                    <h3 style="margin:0 0 10px 0;font-size:16px">إكمال التركيب المجاني</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label>موعد التركيب</label>
                            <select name="appointment_id">
                                <option value="">بدون موعد مسبق</option>
                                @foreach($activeInstallationAppointments as $appointment)
                                    <option value="{{ $appointment->id }}">#{{ $appointment->id }} · {{ $appointment->appointment_date->toDateString() }} · {{ $appointment->appointment_time }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>وقت الإكمال *</label>
                            <input type="datetime-local" name="installed_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                        </div>
                        <div class="field">
                            <label>المنفذ *</label>
                            <select name="installed_by">
                                @foreach($teamUsers as $u)
                                    <option value="{{ $u->id }}" @selected($u->id === auth()->id())>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>الفرع</label>
                            <input name="branch_name" placeholder="اسم الفرع الذي تم تركيبه">
                        </div>
                        <div class="field full">
                            <label>العناصر المركبة من الكتالوج</label>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:6px">
                                @foreach($catalogServices as $service)
                                    <label style="display:flex;align-items:center;gap:8px;border:1px solid var(--nd-border);border-radius:8px;padding:8px">
                                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}">
                                        <span>{{ $service->name_ar }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="field full">
                            <label>عناصر مخصصة</label>
                            <textarea name="custom_item_names" placeholder="كل عنصر في سطر مستقل"></textarea>
                        </div>
                        <div class="field">
                            <label>تاريخ متابعة ما بعد التركيب</label>
                            <input type="date" name="next_follow_up_date" value="{{ now()->addDays(3)->toDateString() }}">
                        </div>
                        <div class="field">
                            <label>الإجراء القادم</label>
                            <input name="next_action" value="متابعة قرار العميل بعد التركيب">
                        </div>
                        <div class="field full">
                            <label style="display:inline-flex;align-items:center;gap:8px">
                                <input type="checkbox" name="no_follow_up" value="1">
                                <span>لا توجد متابعة فورية</span>
                            </label>
                            <input name="no_follow_up_reason" placeholder="سبب عدم جدولة متابعة" style="margin-top:8px">
                        </div>
                        <div class="field full">
                            <label>ملاحظات التركيب</label>
                            <textarea name="notes"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">تسجيل اكتمال التركيب</button>
                </form>

                <div class="list" style="margin-top:16px">
                    @forelse($installations as $installation)
                        <div class="list-row">
                            <span class="badge green">منجز</span>
                            <div class="list-main">
                                <strong>{{ $installation->installed_at->format('Y-m-d H:i') }}@if($installation->branch_name) · {{ $installation->branch_name }} @endif</strong>
                                <small>المنفذ: {{ optional($installation->installedBy)->name ?: 'غير محدد' }}</small>
                                <small>العناصر: {{ $installation->items->pluck('service_name_snapshot')->join('، ') }}</small>
                                @if($installation->appointment_id)
                                    <small>مرتبط بموعد #{{ $installation->appointment_id }}</small>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="muted" style="padding:10px 0">لا يوجد تركيب منجز بعد.</div>
                    @endforelse
                </div>
            </div>

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
                            <small class="muted">هاتف النشاط التجاري</small>
                            <strong style="direction:ltr;text-align:right">{{ $businessPhone }}</strong>
                        </div>
                    </div>
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">جهة الاتصال الأساسية</small>
                            <strong>{{ $contactName ?: 'غير محدد' }}@if($contactPhone) · <span style="direction:ltr;display:inline-block">{{ $contactPhone }}</span>@endif</strong>
                        </div>
                    </div>
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">المنطقة والمدينة</small>
                            <strong>{{ $client->city ?: $client->city_area }}@if($client->area) / {{ $client->area }} @endif</strong>
                            @if($client->city_area)
                                <small>{{ $client->city_area }}</small>
                            @endif
                        </div>
                    </div>
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">نوع النشاط والفئة</small>
                            <strong>{{ $client->business_type ?: $client->business_category }}</strong>
                            <small>{{ $client->business_category }} · {{ $client->number_of_branches ?? 1 }} فروع</small>
                        </div>
                    </div>
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">مصدر العميل</small>
                            <strong>{{ $client->lead_source }}</strong>
                            @if($client->source_reference)
                                <small>{{ $client->source_reference }}</small>
                            @endif
                        </div>
                    </div>
                    @if($client->instagram || $client->website)
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">الحضور الرقمي</small>
                            <strong>@if($client->instagram) {{ $client->instagram }} @endif @if($client->website) · <span style="direction:ltr;display:inline-block">{{ $client->website }}</span> @endif</strong>
                        </div>
                    </div>
                    @endif
                    @if($client->maps_url || $client->location_text)
                    <div class="list-row">
                        <div class="list-main">
                            <small class="muted">الموقع</small>
                            <strong>{{ $client->location_text ?: 'رابط الخريطة' }}</strong>
                            @if($client->maps_url)
                                <small style="direction:ltr;text-align:right">{{ $client->maps_url }}</small>
                            @endif
                        </div>
                    </div>
                    @endif
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

            <div class="card" style="margin-bottom:16px">
                <div class="section-head" style="margin-top:0">
                    <h2>جهات الاتصال</h2>
                    <span class="muted">{{ $client->contacts->count() }} جهات</span>
                </div>
                <div class="list" style="margin-bottom:14px">
                    @forelse($client->contacts as $contact)
                        <div class="list-row">
                            <span class="badge {{ $contact->is_primary ? 'green' : '' }}">{{ $contact->is_primary ? 'أساسية' : 'إضافية' }}</span>
                            <div class="list-main">
                                <strong>{{ $contact->name }}</strong>
                                <small>{{ $contact->role ?: 'بدون دور' }}@if($contact->primary_phone) · <span style="direction:ltr;display:inline-block">{{ $contact->primary_phone }}</span>@endif @if($contact->whatsapp_number) · واتساب <span style="direction:ltr;display:inline-block">{{ $contact->whatsapp_number }}</span>@endif</small>
                            </div>
                        </div>
                    @empty
                        <div class="muted" style="padding:10px 0">لا توجد جهات اتصال منفصلة بعد.</div>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('clients.contacts.store', $client->id) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>الاسم *</label>
                            <input name="name" required>
                        </div>
                        <div class="field">
                            <label>الدور</label>
                            <input name="role" placeholder="مالك، مدير، محاسب">
                        </div>
                        <div class="field">
                            <label>هاتف مباشر</label>
                            <input name="primary_phone" style="direction:ltr;text-align:right">
                        </div>
                        <div class="field">
                            <label>واتساب</label>
                            <input name="whatsapp_number" style="direction:ltr;text-align:right">
                        </div>
                        <div class="field">
                            <label>طريقة التواصل المفضلة</label>
                            <input name="preferred_contact_method" placeholder="اتصال، واتساب، بريد">
                        </div>
                        <div class="field" style="display:flex;align-items:end">
                            <label style="display:inline-flex;align-items:center;gap:8px">
                                <input type="checkbox" name="is_primary" value="1">
                                <span>جهة الاتصال الأساسية</span>
                            </label>
                        </div>
                    </div>
                    <button class="btn btn-soft" style="margin-top:12px" type="submit">إضافة جهة اتصال</button>
                </form>
            </div>

            <div class="card" style="margin-bottom:16px">
                <div class="section-head" style="margin-top:0">
                    <h2>نتائج التواصل</h2>
                    <span class="muted">{{ $contactAttempts->count() }} محاولات</span>
                </div>
                <form method="POST" action="{{ route('clients.contact-attempts.store', $client->id) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>طريقة التواصل *</label>
                            <select name="method" required>
                                <option value="phone">اتصال</option>
                                <option value="whatsapp">واتساب</option>
                                <option value="field_visit">زيارة ميدانية</option>
                                <option value="instagram">Instagram</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>النتيجة *</label>
                            <select name="result" required>
                                @foreach($contactOutcomes as $outcome)
                                    <option value="{{ $outcome }}">{{ $outcome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>تاريخ المتابعة القادمة</label>
                            <input type="date" name="next_follow_up_date">
                        </div>
                        <div class="field">
                            <label>الإجراء القادم</label>
                            <input name="next_action" placeholder="اتصال لاحق، إرسال عرض...">
                        </div>
                        <div class="field full">
                            <label>ملاحظة التواصل</label>
                            <textarea name="note"></textarea>
                        </div>
                        <div class="field">
                            <label style="display:inline-flex;align-items:center;gap:8px">
                                <input type="checkbox" name="close_client" value="1">
                                <span>إغلاق العميل عند عدم الاهتمام</span>
                            </label>
                        </div>
                        <div class="field">
                            <label>سبب الإغلاق</label>
                            <input name="closed_reason" placeholder="اختياري">
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">تسجيل نتيجة التواصل</button>
                </form>
                <div class="list" style="margin-top:14px">
                    @forelse($contactAttempts->take(5) as $attempt)
                        <div class="list-row">
                            <span class="badge">{{ $attempt->result }}</span>
                            <div class="list-main">
                                <strong>{{ $attempt->method }}</strong>
                                <small>{{ $attempt->note ?: 'بدون ملاحظة' }}</small>
                                @if($attempt->next_action || $attempt->next_follow_up_date)
                                    <small>التالي: {{ $attempt->next_action ?: 'غير محدد' }} @if($attempt->next_follow_up_date) · {{ $attempt->next_follow_up_date->toDateString() }} @endif</small>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="muted" style="padding:10px 0">لا توجد نتائج تواصل بعد.</div>
                    @endforelse
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

{{-- SECTION 2: APPOINTMENTS --}}
<div class="mobile-accordion-header" onclick="toggleAccordion('appointments')" id="acc-header-appointments">
    <div style="display:flex;align-items:center;gap:8px">
        <span>📅</span>
        <span>المواعيد</span>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <span class="badge" style="font-size:11px">{{ $appointments->count() }} مواعيد</span>
        <span class="chevron-icon" style="transition:transform 0.2s ease;display:inline-block">▼</span>
    </div>
</div>
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
                    @php
                        $isInstallationAppointment = $appointment->appointment_type === \App\Support\AppointmentTypes::INSTALLATION;
                    @endphp
                    <div class="list-row">
                        <span class="badge {{ $appointment->status === 'completed' ? 'green' : ($appointment->status === 'confirmed' ? 'green' : ($appointment->status === 'cancelled' ? 'red' : 'gold')) }}" @if($isInstallationAppointment) style="background:#e0f2fe;color:#0369a1" @endif>
                            {{ $appointment->status }}
                        </span>
                        <div class="list-main">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <strong>{{ $appointment->appointment_date }} · {{ $appointment->appointment_time }}</strong>
                                @if(isset($appointment->users) && $appointment->users->isNotEmpty())
                                    <div style="display:flex;gap:4px">
                                        @foreach($appointment->users as $attendee)
                                            <span class="badge" style="background:#e0f2fe;color:#0369a1;font-size:11px">👤 {{ $attendee->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <small>{{ $appointmentTypeLabels[$appointment->appointment_type] ?? $appointment->appointment_type }} · {{ $appointment->location ?: 'عن بُعد' }}@if($appointment->branch_name) · {{ $appointment->branch_name }} @endif</small>
                            @if($appointment->notes)
                                <small style="color:var(--nd-ink)">{{ $appointment->notes }}</small>
                            @endif
                            @if($appointment->status !== 'completed' && $appointment->status !== 'cancelled')
                                <form method="POST" action="{{ route('appointments.reschedule', $appointment->id) }}" class="form-grid" style="margin-top:10px">
                                    @csrf
                                    @method('PATCH')
                                    <div class="field">
                                        <label>تاريخ جديد</label>
                                        <input type="date" name="appointment_date" value="{{ $appointment->appointment_date->toDateString() }}" required>
                                    </div>
                                    <div class="field">
                                        <label>وقت جديد</label>
                                        <input type="time" name="appointment_time" value="{{ substr($appointment->appointment_time, 0, 5) }}" required>
                                    </div>
                                    <div class="field">
                                        <label>الموقع</label>
                                        <input name="location" value="{{ $appointment->location }}">
                                    </div>
                                    <div class="field">
                                        <label>الفرع</label>
                                        <input name="branch_name" value="{{ $appointment->branch_name }}">
                                    </div>
                                    <div class="field full">
                                        <button class="btn btn-soft" type="submit">إعادة الجدولة</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                        <div style="display:flex;gap:6px">
                            @if($appointment->status !== 'completed' && $appointment->status !== 'cancelled')
                                @if($isInstallationAppointment)
                                    <form method="POST" action="{{ route('appointments.update', $appointment->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="cancelled">
                                        <select name="next_stage" style="min-width:130px;margin-bottom:6px">
                                            <option value="">المرحلة التالية</option>
                                            <option value="contacting">قيد التواصل</option>
                                            <option value="appointment">موعد</option>
                                            <option value="decision_pending">بانتظار القرار</option>
                                            <option value="prospect">فرصة جديدة</option>
                                        </select>
                                        <button class="btn btn-soft" type="submit">إلغاء التركيب</button>
                                    </form>
                                @else
                                    <a class="btn btn-primary" href="{{ route('appointments.outcome.create', $appointment->id) }}">
                                        تسجيل النتيجة
                                    </a>
                                @endif
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
                            @foreach($appointmentTypeLabels as $type => $typeLabel)
                                <option value="{{ $type }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>المكان / الرابط</label>
                        <input name="location" placeholder="مقر العميل أو رابط الاجتماع">
                    </div>
                    <div class="field">
                        <label>الفرع</label>
                        <input name="branch_name" placeholder="اسم الفرع عند الحاجة">
                    </div>
                    <div class="field full">
                        <label style="font-weight:700">المسؤولون عن الموعد (الحضور)</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px">
                            @foreach($teamUsers ?? \App\Models\User::where('role', 'admin')->get() as $u)
                                <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                                    <input type="checkbox" name="attendees[]" value="{{ $u->id }}" {{ $u->id === auth()->id() ? 'checked' : '' }} style="width:16px;height:16px;accent-color:var(--nd-primary)">
                                    <span>{{ $u->name }}</span>
                                </label>
                            @endforeach
                        </div>
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

{{-- SECTION 3: FOLLOW-UPS --}}
<div class="mobile-accordion-header" onclick="toggleAccordion('followups')" id="acc-header-followups">
    <div style="display:flex;align-items:center;gap:8px">
        <span>📞</span>
        <span>المتابعات الدورية</span>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <span class="badge" style="font-size:11px">{{ $followUps->count() }} متابعات</span>
        <span class="chevron-icon" style="transition:transform 0.2s ease;display:inline-block">▼</span>
    </div>
</div>
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

{{-- SECTION 4: COMMERCIAL OFFERS --}}
<div class="mobile-accordion-header" onclick="toggleAccordion('offers')" id="acc-header-offers">
    <div style="display:flex;align-items:center;gap:8px">
        <span>💼</span>
        <span>العروض التجارية</span>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <span class="badge" style="font-size:11px">{{ $offers->count() }} عروض</span>
        <span class="chevron-icon" style="transition:transform 0.2s ease;display:inline-block">▼</span>
    </div>
</div>
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

{{-- SECTION 5: SUBSCRIPTIONS & PAYMENTS --}}
<div class="mobile-accordion-header" onclick="toggleAccordion('subscriptions')" id="acc-header-subscriptions">
    <div style="display:flex;align-items:center;gap:8px">
        <span>💳</span>
        <span>الاشتراكات والمال</span>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <span class="badge" style="font-size:11px">{{ $payments->count() }} دفعات</span>
        <span class="chevron-icon" style="transition:transform 0.2s ease;display:inline-block">▼</span>
    </div>
</div>
<div id="tab-subscriptions" class="tab-pane" style="display:none">
    <div class="grid grid-3" style="margin-bottom:16px">
        <div class="card">
            <div class="kpi-label">Outstanding</div>
            <div class="kpi-value" style="font-size:18px;color:var(--nd-danger)">{{ \App\Support\Money::fromMinorUnits($receivableSummary['total_outstanding_minor'])->format() }} د.أ</div>
        </div>
        <div class="card">
            <div class="kpi-label">Overdue</div>
            <div class="kpi-value" style="font-size:18px;color:var(--nd-warning)">{{ \App\Support\Money::fromMinorUnits($receivableSummary['overdue_outstanding_minor'])->format() }} د.أ</div>
        </div>
        <div class="card">
            <div class="kpi-label">Unallocated Credit</div>
            <div class="kpi-value" style="font-size:18px;color:var(--nd-success)">{{ \App\Support\Money::fromMinorUnits($receivableSummary['unallocated_credit_minor'])->format() }} د.أ</div>
        </div>
    </div>
    <div class="grid grid-3" style="margin-bottom:16px">
        <div class="card">
            <div class="kpi-label">Payment Credit</div>
            <div class="kpi-value" style="font-size:18px;color:var(--nd-success)">{{ \App\Support\Money::fromMinorUnits($receivableSummary['unallocated_payment_credit_minor'])->format() }} د.أ</div>
        </div>
        <div class="card">
            <div class="kpi-label">Credit Note Balance</div>
            <div class="kpi-value" style="font-size:18px;color:var(--nd-primary)">{{ \App\Support\Money::fromMinorUnits($receivableSummary['available_credit_note_minor'])->format() }} د.أ</div>
        </div>
        <div class="card">
            <div class="kpi-label">Total Customer Credit</div>
            <div class="kpi-value" style="font-size:18px;color:var(--nd-success)">{{ \App\Support\Money::fromMinorUnits($receivableSummary['total_customer_credit_minor'])->format() }} د.أ</div>
        </div>
    </div>
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
                            @php($paymentProjection = $paymentReceivables[$payment->id] ?? null)
                            <span class="badge {{ $paymentProjection && $paymentProjection['is_reversed'] ? 'red' : ($payment->payment_engine_version === 'v2' ? 'blue' : 'green') }}">{{ $paymentProjection && $paymentProjection['is_reversed'] ? 'Reversed' : ($payment->payment_engine_version === 'v2' ? 'V2' : 'محصل') }}</span>
                            <div class="list-main">
                                <strong>{{ $paymentProjection ? $paymentProjection['total'] : $payment->amount }} د.أ</strong>
                                <small class="muted">{{ $payment->received_at ?: $payment->paid_at }} · {{ $payment->payment_method }}@if($payment->reference) · {{ $payment->reference }} @endif</small>
                                @if($paymentProjection)
                                    <small>مخصص: {{ $paymentProjection['allocated'] }} د.أ · مسترد: {{ $paymentProjection['refunded'] }} د.أ · غير مخصص: {{ $paymentProjection['unallocated'] }} د.أ</small>
                                @endif
                                @if($payment->reversal)
                                    <small style="color:var(--nd-danger)">تم عكس الدفعة: {{ $payment->reversal->reason }}</small>
                                @endif
                                @if($payment->notes)<small>{{ $payment->notes }}</small>@endif
                                @if($paymentProjection && ! $paymentProjection['is_reversed'] && $paymentProjection['unallocated_minor'] > 0)
                                    <form method="POST" action="{{ route('payments.auto-allocate', $payment) }}" style="margin-top:8px">
                                        @csrf
                                        <button class="btn btn-ghost" type="submit">تخصيص تلقائي للأقدم</button>
                                    </form>
                                    @can(\App\Support\FinancialPermissions::ISSUE_REFUNDS)
                                        <form method="POST" action="{{ route('payments.refunds.store', $payment) }}" style="margin-top:8px;border-top:1px dashed var(--nd-border);padding-top:8px" onsubmit="return confirm('الاسترداد يمثل مالاً عائداً للعميل وليس عكس دفعة. هل تريد المتابعة؟')">
                                            @csrf
                                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                                <input name="amount" required placeholder="{{ $paymentProjection['unallocated'] }}" style="max-width:120px">
                                                <select name="financial_account_id" required style="max-width:180px">
                                                    @foreach($activeFinancialAccounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="refund_method" required style="max-width:150px">
                                                    @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                                                        <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="datetime-local" name="refunded_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required style="max-width:210px">
                                                <input name="reference" placeholder="مرجع الاسترداد" style="max-width:160px">
                                                <input name="reason" required placeholder="سبب الاسترداد" style="max-width:220px">
                                                <button class="btn btn-soft" type="submit">استرداد من رصيد الدفعة</button>
                                            </div>
                                        </form>
                                    @endcan
                                @endif
                                @if($payment->allocations->isNotEmpty())
                                    <div style="margin-top:8px;display:grid;gap:6px">
                                        @foreach($payment->allocations as $allocation)
                                            <div style="border:1px dashed var(--nd-border);border-radius:8px;padding:8px">
                                                <small>
                                                    <span class="badge {{ $allocation->reversal ? 'red' : 'green' }}">{{ $allocation->reversal ? 'Reversed' : 'Active' }}</span>
                                                    {{ optional($allocation->invoice)->invoice_number }} · {{ \App\Support\Money::fromMinorUnits($allocation->amount_minor)->format() }} د.أ
                                                </small>
                                                @if($allocation->reversal)
                                                    <small style="color:var(--nd-danger)">سبب العكس: {{ $allocation->reversal->reason }}</small>
                                                @else
                                                    @can(\App\Support\FinancialPermissions::MANAGE_COLLECTION_CORRECTIONS)
                                                        <form method="POST" action="{{ route('payment-allocations.reverse', $allocation) }}" style="margin-top:6px" onsubmit="return confirm('عكس التخصيص سيعيد فتح رصيد الفاتورة. هذا ليس استرداداً للعميل. هل تريد المتابعة؟')">
                                                            @csrf
                                                            <input name="reason" required placeholder="سبب عكس التخصيص" style="max-width:260px">
                                                            <button class="btn btn-ghost" type="submit">عكس التخصيص</button>
                                                        </form>
                                                    @endcan
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if($paymentProjection && ! $paymentProjection['is_reversed'] && $payment->payment_engine_version === 'v2')
                                    @can(\App\Support\FinancialPermissions::MANAGE_COLLECTION_CORRECTIONS)
                                        <form method="POST" action="{{ route('payments.reverse', $payment) }}" style="margin-top:8px" onsubmit="return confirm('عكس الدفعة هو تصحيح سجل فقط وليس استرداداً نقدياً للعميل. يجب عكس كل التخصيصات أولاً. هل تريد المتابعة؟')">
                                            @csrf
                                            <input name="reason" required placeholder="سبب عكس الدفعة" style="max-width:260px">
                                            <button class="btn btn-ghost" type="submit">عكس الدفعة</button>
                                        </form>
                                    @endcan
                                @endif
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

            <div class="card" style="margin-top:16px">
                <div class="section-head" style="margin-top:0">
                    <h2>الفواتير الجديدة</h2>
                    <span class="muted">{{ $invoices->count() }} فاتورة</span>
                </div>
                <div class="list">
                    @forelse($invoices as $invoice)
                        <div class="list-row" style="align-items:flex-start">
                            @php($invoiceProjection = $invoiceReceivables[$invoice->id] ?? null)
                            <span class="badge {{ $invoice->status === 'issued' ? 'blue' : ($invoice->status === 'voided' ? 'red' : 'gold') }}">
                                {{ $invoice->status }}
                            </span>
                            <div class="list-main">
                                <strong class="ltr" style="direction:ltr">{{ $invoice->invoice_number ?? ('INV-DRAFT-'.$invoice->id) }}</strong>
                                <small class="muted">
                                    {{ $invoice->subscription_id ? 'اشتراك' : 'عمل إضافي' }}
                                    · إصدار {{ $invoice->issue_date?->format('Y-m-d') }}
                                    · استحقاق {{ $invoice->due_date?->format('Y-m-d') }}
                                </small>
                                <strong style="color:var(--nd-primary)">{{ \App\Support\Money::fromMinorUnits($invoice->total_minor)->format() }} د.أ</strong>
                                @if($invoiceProjection)
                                    <small>دفعات: {{ $invoiceProjection['allocated'] }} د.أ · رصيد دائن: {{ $invoiceProjection['credit_applied'] }} د.أ · مستحق: {{ $invoiceProjection['outstanding'] }} د.أ · {{ $invoiceProjection['settlement_status'] }}</small>
                                @endif
                                @if($invoice->status === 'issued' && $invoiceProjection && $invoiceProjection['outstanding_minor'] > 0)
                                    <form method="POST" action="{{ route('clients.collections.payments.store', $client) }}" style="margin-top:8px">
                                        @csrf
                                        <input type="hidden" name="allocations[0][invoice_id]" value="{{ $invoice->id }}">
                                        <input type="hidden" name="allocations[0][amount]" value="{{ $invoiceProjection['outstanding'] }}">
                                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                                            <input name="amount" value="{{ $invoiceProjection['outstanding'] }}" required style="max-width:120px">
                                            <select name="financial_account_id" required style="max-width:180px">
                                                @foreach($activeFinancialAccounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                                @endforeach
                                            </select>
                                            <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required style="max-width:210px">
                                            <select name="payment_method" required style="max-width:150px">
                                                @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                                                    <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-primary" type="submit">دفع سريع</button>
                                        </div>
                                    </form>
                                @endif
                                @if($invoice->status === 'issued')
                                    <form method="POST" action="{{ route('invoices.void', $invoice) }}" style="margin-top:8px" onsubmit="return confirm('هل تريد إلغاء هذه الفاتورة مع الحفاظ على السجل؟')">
                                        @csrf
                                        <input type="text" name="void_reason" placeholder="سبب الإلغاء" required style="max-width:260px">
                                        <button class="btn btn-ghost" type="submit">إلغاء الفاتورة</button>
                                    </form>
                                @elseif($invoice->status === 'voided')
                                    <small style="color:var(--nd-danger)">سبب الإلغاء: {{ $invoice->void_reason }}</small>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="muted" style="padding:14px 0">لا توجد فواتير جديدة لهذا العميل بعد.</div>
                    @endforelse
                </div>
            </div>

            <div class="card" style="margin-top:16px">
                <div class="section-head" style="margin-top:0">
                    <h2>إشعارات الدائن</h2>
                    <span class="muted">{{ $creditNotes->count() }} إشعارات</span>
                </div>

                @can(\App\Support\FinancialPermissions::MANAGE_CREDIT_NOTES)
                    <form method="POST" action="{{ route('clients.credit-notes.store', $client) }}" style="margin-bottom:16px;border-bottom:1px solid var(--nd-border);padding-bottom:14px">
                        @csrf
                        <div class="form-grid">
                            <div class="field">
                                <label>تاريخ الإصدار *</label>
                                <input type="date" name="issue_date" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="field">
                                <label>فاتورة أصلية اختيارية</label>
                                <select name="original_invoice_id">
                                    <option value="">رصيد عام / Goodwill</option>
                                    @foreach($invoices as $invoice)
                                        @if($invoice->status === 'issued')
                                            <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · {{ \App\Support\Money::fromMinorUnits($invoice->total_minor)->format() }} د.أ</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="field full">
                                <label>السبب *</label>
                                <input name="reason" required placeholder="خصم تجاري / تصحيح استحقاق / رصيد حسن نية">
                            </div>
                            <div class="field full" style="background:#f8fafc;border:1px solid var(--nd-border);border-radius:12px;padding:12px">
                                <label>سطر إشعار الدائن *</label>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>الوصف</label>
                                        <input name="lines[0][description]" required placeholder="Credit adjustment">
                                    </div>
                                    <div class="field">
                                        <label>المبلغ قبل الضريبة</label>
                                        <input name="lines[0][subtotal_jod]" required placeholder="20.000">
                                    </div>
                                    <div class="field">
                                        <label>الضريبة</label>
                                        <input name="lines[0][tax_jod]" value="0.000">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary" style="margin-top:12px" type="submit">إنشاء وإصدار إشعار دائن</button>
                    </form>
                @endcan

                <div class="list">
                    @forelse($creditNotes as $creditNote)
                        @php($creditProjection = $creditNoteReceivables[$creditNote->id] ?? null)
                        <div class="list-row" style="align-items:flex-start">
                            <span class="badge {{ $creditNote->status === 'issued' ? 'blue' : ($creditNote->status === 'voided' ? 'red' : 'gold') }}">{{ $creditNote->status }}</span>
                            <div class="list-main">
                                <strong class="ltr" style="direction:ltr">{{ $creditNote->credit_note_number }}</strong>
                                <small class="muted">{{ $creditNote->issue_date?->format('Y-m-d') }} · {{ $creditNote->reason }}</small>
                                @if($creditProjection)
                                    <small>الإجمالي {{ $creditProjection['total'] }} د.أ · مطبق {{ $creditProjection['applied'] }} د.أ · مسترد {{ $creditProjection['refunded'] }} د.أ · متاح {{ $creditProjection['available'] }} د.أ</small>
                                @endif
                                @if($creditNote->originalInvoice)
                                    <small>الفاتورة الأصلية: {{ $creditNote->originalInvoice->invoice_number }}</small>
                                @endif

                                @can(\App\Support\FinancialPermissions::MANAGE_CREDIT_NOTES)
                                    @if($creditNote->status === 'issued' && $creditProjection && $creditProjection['available_minor'] > 0)
                                        <form method="POST" action="{{ route('credit-notes.applications.store', $creditNote) }}" style="margin-top:8px">
                                            @csrf
                                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                                <select name="invoice_id" required style="max-width:240px">
                                                    @foreach($invoices as $invoice)
                                                        @php($projection = $invoiceReceivables[$invoice->id] ?? null)
                                                        @if($invoice->status === 'issued' && $projection && $projection['outstanding_minor'] > 0)
                                                            <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · مستحق {{ $projection['outstanding'] }} د.أ</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                <input name="amount" required placeholder="{{ $creditProjection['available'] }}" style="max-width:120px">
                                                <button class="btn btn-ghost" type="submit">تطبيق الرصيد</button>
                                            </div>
                                        </form>
                                    @endif
                                @endcan

                                @if($creditNote->applications->isNotEmpty())
                                    <div style="margin-top:8px;display:grid;gap:6px">
                                        @foreach($creditNote->applications as $application)
                                            <div style="border:1px dashed var(--nd-border);border-radius:8px;padding:8px">
                                                <small>
                                                    <span class="badge {{ $application->reversal ? 'red' : 'green' }}">{{ $application->reversal ? 'Reversed' : 'Active' }}</span>
                                                    {{ optional($application->invoice)->invoice_number }} · {{ \App\Support\Money::fromMinorUnits($application->amount_minor)->format() }} د.أ
                                                </small>
                                                @if($application->reversal)
                                                    <small style="color:var(--nd-danger)">سبب العكس: {{ $application->reversal->reason }}</small>
                                                @else
                                                    @can(\App\Support\FinancialPermissions::MANAGE_CREDIT_NOTES)
                                                        <form method="POST" action="{{ route('credit-note-applications.reverse', $application) }}" style="margin-top:6px" onsubmit="return confirm('عكس تطبيق الرصيد يعيد فتح ذمة الفاتورة ويعيد الرصيد المتاح. هل تريد المتابعة؟')">
                                                            @csrf
                                                            <input name="reason" required placeholder="سبب عكس تطبيق الرصيد" style="max-width:260px">
                                                            <button class="btn btn-ghost" type="submit">عكس تطبيق الرصيد</button>
                                                        </form>
                                                    @endcan
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @can(\App\Support\FinancialPermissions::ISSUE_REFUNDS)
                                    @if($creditNote->status === 'issued' && $creditProjection && $creditProjection['available_minor'] > 0)
                                        <form method="POST" action="{{ route('credit-notes.refunds.store', $creditNote) }}" style="margin-top:8px;border-top:1px dashed var(--nd-border);padding-top:8px" onsubmit="return confirm('الاسترداد من إشعار دائن يمثل مالاً عائداً للعميل وليس عكس تطبيق. هل تريد المتابعة؟')">
                                            @csrf
                                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                                <input name="amount" required placeholder="{{ $creditProjection['available'] }}" style="max-width:120px">
                                                <select name="financial_account_id" required style="max-width:180px">
                                                    @foreach($activeFinancialAccounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="refund_method" required style="max-width:150px">
                                                    @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                                                        <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="datetime-local" name="refunded_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required style="max-width:210px">
                                                <input name="reference" placeholder="مرجع الاسترداد" style="max-width:160px">
                                                <input name="reason" required placeholder="سبب الاسترداد" style="max-width:220px">
                                                <button class="btn btn-soft" type="submit">استرداد من إشعار دائن</button>
                                            </div>
                                        </form>
                                    @endif
                                @endcan

                                @can(\App\Support\FinancialPermissions::MANAGE_CREDIT_NOTES)
                                    @if($creditNote->status === 'issued' && $creditProjection && $creditProjection['applied_minor'] <= 0 && $creditProjection['refunded_minor'] <= 0)
                                        <form method="POST" action="{{ route('credit-notes.void', $creditNote) }}" style="margin-top:8px" onsubmit="return confirm('سيتم إلغاء إشعار الدائن دون حذف سطوره. هل تريد المتابعة؟')">
                                            @csrf
                                            <input name="void_reason" required placeholder="سبب الإلغاء" style="max-width:260px">
                                            <button class="btn btn-ghost" type="submit">إلغاء إشعار الدائن</button>
                                        </form>
                                    @elseif($creditNote->status === 'voided')
                                        <small style="color:var(--nd-danger)">سبب الإلغاء: {{ $creditNote->void_reason }}</small>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    @empty
                        <div class="muted" style="padding:14px 0">لا توجد إشعارات دائن لهذا العميل بعد.</div>
                    @endforelse
                </div>
            </div>

            <div class="card" style="margin-top:16px">
                <div class="section-head" style="margin-top:0">
                    <h2>سجل الاستردادات</h2>
                    <span class="muted">{{ $refunds->count() }} استردادات</span>
                </div>
                <div class="list">
                    @forelse($refunds as $refund)
                        <div class="list-row">
                            <span class="badge green">Refund</span>
                            <div class="list-main">
                                <strong class="ltr" style="direction:ltr">{{ $refund->refund_number }}</strong>
                                <small>{{ \App\Support\Money::fromMinorUnits($refund->amount_minor)->format() }} د.أ · {{ $refund->refund_method }} · {{ $refund->refunded_at?->format('Y-m-d H:i') }}</small>
                                <small class="muted">المصدر: {{ $refund->payment_id ? ('دفعة #'.$refund->payment_id) : ('إشعار دائن '.optional($refund->creditNote)->credit_note_number) }} · {{ $refund->reason }}</small>
                            </div>
                        </div>
                    @empty
                        <div class="muted" style="padding:14px 0">لا يوجد سجل استردادات بعد.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Record Payment or Convert --}}
        <div>
            {{-- Record Payment Form --}}
            <div class="card form-card" style="margin-bottom:16px">
                <h3 style="margin-top:0">تسجيل دفعة V2 جديدة لهذا العميل</h3>
                <form method="POST" action="{{ route('clients.collections.payments.store', $client) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>المبلغ المدفوع (د.أ) *</label>
                            <input name="amount" required placeholder="450.000">
                        </div>
                        <div class="field">
                            <label>الحساب المالي المستلم *</label>
                            <select name="financial_account_id" required>
                                @foreach($activeFinancialAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>طريقة الدفع *</label>
                            <select name="payment_method" required>
                                @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                                    <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field full">
                            <label>تاريخ ووقت التحصيل *</label>
                            <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                        </div>
                        <div class="field">
                            <label>مرجع الدفعة</label>
                            <input name="reference" placeholder="رقم إيصال / تحويل">
                        </div>
                        <div class="field">
                            <label>تخصيص تلقائي</label>
                            <label style="display:flex;align-items:center;gap:8px;margin-top:8px">
                                <input type="checkbox" name="auto_allocate_oldest" value="1">
                                <span>أقدم الفواتير أولاً</span>
                            </label>
                        </div>
                        <div class="field full">
                            <label>تخصيص يدوي على فاتورة</label>
                            <div style="display:grid;grid-template-columns:minmax(0,1fr) 140px;gap:8px">
                                <select name="allocations[0][invoice_id]">
                                    <option value="">بدون تخصيص فوري</option>
                                    @foreach($invoices as $invoice)
                                        @php($projection = $invoiceReceivables[$invoice->id] ?? null)
                                        @if($invoice->status === 'issued' && $projection && $projection['outstanding_minor'] > 0)
                                            <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · مستحق {{ $projection['outstanding'] }} د.أ</option>
                                        @endif
                                    @endforeach
                                </select>
                                <input name="allocations[0][amount]" placeholder="0.000">
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:14px" type="submit">تسجيل دفعة V2</button>
                </form>
            </div>

            @if($paymentReceivables->where('unallocated_minor', '>', 0)->isNotEmpty())
            <div class="card form-card" style="margin-bottom:16px">
                <h3 style="margin-top:0">تخصيص رصيد غير مستخدم</h3>
                @foreach($payments as $payment)
                    @php($paymentProjection = $paymentReceivables[$payment->id] ?? null)
                    @if($paymentProjection && ! $paymentProjection['is_reversed'] && $paymentProjection['unallocated_minor'] > 0)
                        <form method="POST" action="{{ route('payments.allocations.store', $payment) }}" style="border-top:1px solid var(--nd-border);padding-top:10px;margin-top:10px">
                            @csrf
                            <small class="muted">دفعة #{{ $payment->id }} · متاح {{ $paymentProjection['unallocated'] }} د.أ</small>
                            <div class="form-grid" style="margin-top:8px">
                                <div class="field">
                                    <label>الفاتورة</label>
                                    <select name="invoice_id" required>
                                        @foreach($invoices as $invoice)
                                            @php($projection = $invoiceReceivables[$invoice->id] ?? null)
                                            @if($invoice->status === 'issued' && $projection && $projection['outstanding_minor'] > 0)
                                                <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · {{ $projection['outstanding'] }} د.أ</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>المبلغ</label>
                                    <input name="amount" required placeholder="{{ $paymentProjection['unallocated'] }}">
                                </div>
                            </div>
                            <button class="btn btn-ghost" style="margin-top:8px" type="submit">تخصيص الرصيد</button>
                        </form>
                    @endif
                @endforeach
            </div>
            @endif

            @if(auth()->user()->isAdmin())
            <div class="card form-card" style="margin-bottom:16px;border:2px solid var(--nd-primary)">
                <h3 style="margin-top:0;color:var(--nd-primary)">Start Paid Subscription</h3>
                <p class="muted">ينشئ اشتراك V2 وفاتورة أولى فقط، ولا يسجل أي دفعة.</p>
                <form method="POST" action="{{ route('clients.paid-subscriptions.store', $client->id) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field full">
                            <label>الباقة والسعر *</label>
                            <select name="plan_price_id" required>
                                @foreach($sellablePlans as $plan)
                                    @foreach($plan->activePrices as $price)
                                        <option value="{{ $price->id }}">
                                            {{ $plan->name_ar }} · {{ $price->billing_interval === 'annual' ? 'سنوي' : 'شهري' }}
                                            · {{ \App\Support\Money::fromMinorUnits($price->amount_minor)->format() }} د.أ
                                            @if($price->setup_fee_minor > 0)
                                                · تأسيس {{ \App\Support\Money::fromMinorUnits($price->setup_fee_minor)->format() }} د.أ
                                            @endif
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>عدد الفروع / الوحدات *</label>
                            <input type="number" name="quantity" min="1" value="{{ $client->number_of_branches ?? 1 }}" required>
                        </div>
                        <div class="field">
                            <label>تاريخ بداية الاشتراك *</label>
                            <input type="date" name="start_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>خصم تفاوضي دقيق (د.أ)</label>
                            <input name="discount_jod" placeholder="0.000">
                        </div>
                        <div class="field">
                            <label>سبب الخصم</label>
                            <input name="discount_reason" placeholder="مثال: عرض افتتاحي">
                        </div>
                        <div class="field full">
                            <label>ملاحظات الفاتورة</label>
                            <textarea name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:14px;width:100%" type="submit">بدء الاشتراك المدفوع وإصدار الفاتورة</button>
                </form>
            </div>

            <div class="card form-card" style="margin-bottom:16px">
                <h3 style="margin-top:0">فاتورة عمل إضافي / One-Time Work</h3>
                <form method="POST" action="{{ route('clients.one-time-invoices.store', $client->id) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>تاريخ الإصدار *</label>
                            <input type="date" name="issue_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>تاريخ الاستحقاق *</label>
                            <input type="date" name="due_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field full">
                            <label>وصف الفاتورة</label>
                            <input name="description" placeholder="مثال: تطوير موقع وهوية بصرية">
                        </div>
                        <div class="field full" style="background:#f8fafc;border:1px solid var(--nd-border);border-radius:12px;padding:12px">
                            <label>السطر الأول *</label>
                            <input type="hidden" name="lines[0][line_type]" value="one_time_service">
                            <div class="form-grid">
                                <div class="field">
                                    <label>الوصف</label>
                                    <input name="lines[0][description]" required placeholder="Website development">
                                </div>
                                <div class="field">
                                    <label>الكمية</label>
                                    <input type="number" name="lines[0][quantity]" min="1" value="1" required>
                                </div>
                                <div class="field">
                                    <label>سعر الوحدة (د.أ)</label>
                                    <input name="lines[0][unit_price_jod]" required placeholder="120.000">
                                </div>
                                <div class="field">
                                    <label>خصم السطر (د.أ)</label>
                                    <input name="lines[0][discount_jod]" placeholder="0.000">
                                </div>
                                <div class="field">
                                    <label>الضريبة bps</label>
                                    <input type="number" name="lines[0][tax_rate_bps]" min="0" max="10000" placeholder="1600">
                                </div>
                            </div>
                        </div>
                        <div class="field full" style="background:#f8fafc;border:1px solid var(--nd-border);border-radius:12px;padding:12px">
                            <label>سطر إضافي اختياري</label>
                            <input type="hidden" name="lines[1][line_type]" value="custom">
                            <div class="form-grid">
                                <div class="field">
                                    <label>الوصف</label>
                                    <input name="lines[1][description]" placeholder="Domain / training / hardware">
                                </div>
                                <div class="field">
                                    <label>الكمية</label>
                                    <input type="number" name="lines[1][quantity]" min="1" value="1">
                                </div>
                                <div class="field">
                                    <label>سعر الوحدة (د.أ)</label>
                                    <input name="lines[1][unit_price_jod]" placeholder="20.000">
                                </div>
                                <div class="field">
                                    <label>خصم السطر (د.أ)</label>
                                    <input name="lines[1][discount_jod]" placeholder="0.000">
                                </div>
                                <div class="field">
                                    <label>الضريبة bps</label>
                                    <input type="number" name="lines[1][tax_rate_bps]" min="0" max="10000" placeholder="1600">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:14px;width:100%" type="submit">إنشاء وإصدار فاتورة العمل الإضافي</button>
                </form>
            </div>
            @endif

            {{-- Convert to Subscriber Form --}}
            @if($client->status === 'prospect')
            <div class="card form-card" id="convert-section" style="border:2px solid var(--nd-primary)">
                <h3 style="margin-top:0;color:var(--nd-primary)">تحويل العميل إلى مشترك رسمي</h3>
                <p class="muted">سيتم إنشاء اشتراك نشط وتوليد جدول الأقساط والاستحقاقات وحساب الضرائب والخصومات آلياً.</p>
                <form method="POST" action="{{ route('clients.convert', $client->id) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>نوع الفوترة *</label>
                            <select name="billing_type" required>
                                <option value="monthly">شهري (12 قسط شهري)</option>
                                <option value="annual">سنوي (دفعة سنوية مع خصم 10%)</option>
                                <option value="installment">تقسيط مخصص</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>السعر الأساسي المخصص (د.أ)</label>
                            <input type="number" step="0.001" name="total_price" placeholder="اتركه فارغاً للاحتساب من الخدمات">
                            <small class="muted">إذا حُدد سيتم اعتماده كأساس، وإلا يُحسب من مجموع الخدمات</small>
                        </div>
                        <div class="field">
                            <label>رسوم التأسيس والربط (Setup Fee د.أ)</label>
                            <input type="number" step="0.001" name="setup_fee" value="0.000" placeholder="0.000">
                        </div>
                        <div class="field">
                            <label>تاريخ بداية الاشتراك *</label>
                            <input type="date" name="start_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>يوم استحقاق القسط الشهري</label>
                            <select name="monthly_due_day">
                                <option value="1">1 من كل شهر</option>
                                <option value="5">5 من كل شهر</option>
                                <option value="15">15 من كل شهر</option>
                                <option value="30">30 من كل شهر</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>عدد الأقساط (في حال التقسيط المخصص)</label>
                            <input type="number" name="installments_count" min="2" max="12" value="3">
                        </div>

                        {{-- Data-driven Services Selection --}}
                        <div class="field full" style="margin-top:8px">
                            <label style="font-size:13px;font-weight:800;color:var(--nd-ink);margin-bottom:8px">الخدمات المختارة من الكتالوج (Service Catalog)</label>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:10px;background:#f8fafc;padding:12px;border-radius:12px;border:1px solid var(--nd-border)">
                                @foreach($catalogServices ?? [] as $service)
                                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;padding:8px;background:#fff;border-radius:8px;border:1px solid #e2e8f0">
                                        <input type="checkbox" name="services[]" value="{{ $service->id }}" style="width:18px;height:18px;margin-top:2px;accent-color:var(--nd-primary)">
                                        <div>
                                            <div style="font-weight:700;font-size:13px;color:var(--nd-ink)">{{ $service->name_ar }}</div>
                                            <div style="font-size:11px;color:var(--nd-primary);font-weight:700">{{ number_format($service->default_price, 3) }} د.أ</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:16px;width:100%" type="submit">
                        ★ تأكيد تحويل العميل وتوليد جدول الأقساط
                    </button>
                </form>
            </div>
            @endif

            {{-- Active Subscriptions with Renewal and Cancellation --}}
            @if(isset($subscriptions) && $subscriptions->count() > 0)
            <div class="card" style="margin-top:18px">
                <div class="section-head" style="margin-top:0">
                    <h2>الاشتراكات المعتمدة</h2>
                    <span class="muted">{{ $subscriptions->count() }} اشتراك</span>
                </div>
                <div class="list">
                    @foreach($subscriptions as $sub)
                        <div class="list-row" style="flex-direction:column;align-items:stretch;gap:8px;padding:14px;background:#f8fafc;border-radius:12px;margin-bottom:10px;border:1px solid var(--nd-border)">
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                                <div>
                                    <span class="badge {{ $sub->status === 'active' ? 'green' : ($sub->status === 'cancelled' ? 'red' : 'gold') }}">
                                        {{ $sub->status === 'active' ? 'نشط' : ($sub->status === 'cancelled' ? 'ملغي' : $sub->status) }}
                                    </span>
                                    <strong style="display:inline-block;margin-right:8px;font-size:15px">
                                        {{ $sub->billing_type === 'annual' ? 'سنوي' : ($sub->billing_type === 'monthly' ? 'شهري' : 'تقسيط') }}
                                        (إصدار v{{ $sub->version ?? 1 }})
                                    </strong>
                                </div>
                                <div style="font-weight:800;font-size:16px;color:var(--nd-primary)">
                                    {{ number_format($sub->grand_total ?? $sub->total_price, 3) }} د.أ
                                </div>
                            </div>
                            <div class="muted" style="font-size:12px">
                                يبدأ: {{ $sub->start_date }}
                                @if($sub->renewal_date) · التجديد: {{ $sub->renewal_date }} @endif
                                @if($sub->setup_fee > 0) · رسوم التأسيس: {{ number_format($sub->setup_fee, 3) }} د.أ @endif
                                @if($sub->discount_amount > 0) · الخصم: {{ number_format($sub->discount_amount, 3) }} د.أ ({{ $sub->annual_discount_percentage }}%) @endif
                                @if($sub->tax_amount > 0) · الضريبة: {{ number_format($sub->tax_amount, 3) }} د.أ ({{ $sub->tax_percentage }}%) @endif
                            </div>
                            @if($sub->status === 'cancelled')
                                <div style="font-size:12px;color:var(--nd-danger);background:#fef2f2;padding:6px 10px;border-radius:6px">
                                    ⛔ تم الإلغاء بتاريخ {{ $sub->cancelled_at }}. السبب: {{ $sub->cancellation_reason ?: 'غير محدد' }}
                                </div>
                            @elseif($sub->status === 'active')
                                <div style="display:flex;gap:8px;margin-top:6px;justify-content:flex-end;flex-wrap:wrap">
                                    @can('create', \App\Models\Contract::class)
                                        <form method="POST" action="{{ route('contracts.store', ['client' => $client->id, 'subscription' => $sub->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-soft" style="font-size:11px;padding:6px 12px;color:var(--nd-primary);font-weight:700">
                                                📄 توليد عقد رسمي
                                            </button>
                                        </form>
                                    @endcan
                                    <form method="POST" action="{{ route('subscriptions.renew', $sub->id) }}" onsubmit="return confirm('هل تريد بالتأكيد تجديد هذا الاشتراك؟ سيتم توليد اشتراك جديد للمرحلة القادمة.')">
                                        @csrf
                                        <button type="submit" class="btn btn-soft" style="font-size:11px;padding:6px 12px">
                                            🔄 تجديد الاشتراك
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('subscriptions.cancel', $sub->id) }}" onsubmit="return confirm('هل تريد بالتأكيد إلغاء هذا الاشتراك؟ سيتم إيقاف المطالبات والتنبيهات المستقبلية.')">
                                        @csrf
                                        <input type="hidden" name="cancellation_reason" value="طلب العميل / إنهاء الخدمة">
                                        <button type="submit" class="btn btn-danger" style="font-size:11px;padding:6px 12px">
                                            ⛔ إلغاء الاشتراك
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Official Contracts Section --}}
    <div class="card" style="margin-top:20px">
        <div class="section-head" style="margin-top:0">
            <div>
                <h2>العقود والاتفاقيات الرسمية (Notify Contracts)</h2>
                <small class="muted">عقود تشغيلية تفصيلية تشمل 22 بنداً قانونياً وتشغيلياً مع الملاحق الثلاثة وحفظ اللقطات التاريخية (Snapshots)</small>
            </div>
            <span class="badge" style="background:#eff6ff;color:#0C86ED;border:1px solid #bfdbfe">{{ $contracts->count() }} عقود</span>
        </div>
        <div class="list">
            @forelse($contracts as $contract)
                <div class="list-row" style="flex-direction:column;align-items:stretch;gap:10px;padding:14px;background:#f8fafc;border-radius:12px;margin-bottom:10px;border:1px solid var(--nd-border)">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                        <div style="display:flex;align-items:center;gap:10px">
                            <strong style="font-size:15px;direction:ltr" class="ltr">{{ $contract->contract_number }}</strong>
                            @if($contract->status === 'issued')
                                <span class="badge green">معتمد ورسمي</span>
                            @elseif($contract->status === 'voided')
                                <span class="badge red">ملغي (Voided)</span>
                            @elseif($contract->status === 'superseded')
                                <span class="badge">مستبدل</span>
                            @else
                                <span class="badge gold">مسودة قيد المراجعة</span>
                            @endif
                            <span class="muted" style="font-size:11px">نسخة v{{ $contract->template_version }}</span>
                        </div>
                        <div style="font-size:12px;color:var(--nd-muted)">
                            تاريخ الإنشاء: {{ $contract->created_at->format('Y-m-d') }}
                            @if($contract->issued_at) · الاعتماد: {{ $contract->issued_at->format('Y-m-d') }} @endif
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;border-top:1px dashed #e2e8f0;padding-top:10px">
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="{{ route('contracts.preview', $contract->id) }}" target="_blank" class="btn btn-soft" style="font-size:11px;padding:5px 10px;display:inline-flex;align-items:center;gap:4px">
                                👁️ معاينة العقد
                            </a>
                            <a href="{{ route('contracts.print', $contract->id) }}" target="_blank" class="btn btn-soft" style="font-size:11px;padding:5px 10px;display:inline-flex;align-items:center;gap:4px">
                                🖨️ طباعة / حفظ PDF
                            </a>
                            <a href="{{ route('contracts.download', $contract->id) }}" class="btn btn-ghost" style="font-size:11px;padding:5px 10px;display:inline-flex;align-items:center;gap:4px">
                                💾 تحميل المستند
                            </a>
                        </div>

                        @if(auth()->user()->isAdmin())
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                @if($contract->status === 'draft')
                                    <form method="POST" action="{{ route('contracts.issue', $contract->id) }}" onsubmit="return confirm('هل أنت متأكد من اعتماد وإصدار هذا العقد رسمياً؟')">
                                        @csrf
                                        <button type="submit" class="btn btn-primary" style="font-size:11px;padding:5px 12px;background:#10b981;border-color:#059669">
                                            ✓ اعتماد وإصدار
                                        </button>
                                    </form>
                                @endif

                                @if($contract->status === 'issued')
                                    <form method="POST" action="{{ route('contracts.supersede', $contract->id) }}" onsubmit="return confirm('هل تريد استبدال هذا العقد بإصدار جديد محدث؟ سيتم نقل الحالة الحالية إلى مستبدل.')">
                                        @csrf
                                        <button type="submit" class="btn btn-soft" style="font-size:11px;padding:5px 10px">
                                            🔄 استبدال (Supersede)
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('contracts.void', $contract->id) }}" onsubmit="var r = prompt('أدخل سبب إلغاء هذا العقد:'); if(r){ this.reason.value = r; return true; } return false;">
                                        @csrf
                                        <input type="hidden" name="reason" value="">
                                        <button type="submit" class="btn btn-danger" style="font-size:11px;padding:5px 10px">
                                            ⛔ إلغاء (Void)
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="muted" style="padding:14px 0;text-align:center">
                    لا توجد عقود صادرة لهذا العميل بعد. يمكنك توليد مسودة عقد رسمي من أي اشتراك نشط أعلاه.
                </div>
            @endforelse
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
    document.querySelectorAll('.mobile-accordion-header').forEach(function(hdr) {
        hdr.classList.remove('active');
        var chevron = hdr.querySelector('.chevron-icon');
        if (chevron) chevron.style.transform = 'rotate(0deg)';
    });

    var targetPane = document.getElementById('tab-' + tabName);
    var targetBtn = document.getElementById('tab-btn-' + tabName);
    var targetHeader = document.getElementById('acc-header-' + tabName);

    if (targetPane) targetPane.style.display = 'block';
    if (targetBtn) {
        targetBtn.classList.remove('btn-ghost');
        targetBtn.classList.add('btn-primary');
    }
    if (targetHeader) {
        targetHeader.classList.add('active');
        var chevron = targetHeader.querySelector('.chevron-icon');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
    }
}

function toggleAccordion(tabName) {
    var targetPane = document.getElementById('tab-' + tabName);
    var targetHeader = document.getElementById('acc-header-' + tabName);
    if (!targetPane) return;

    var isOpen = targetPane.style.display === 'block';
    if (isOpen) {
        targetPane.style.display = 'none';
        if (targetHeader) {
            targetHeader.classList.remove('active');
            var chevron = targetHeader.querySelector('.chevron-icon');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    } else {
        switchTab(tabName);
        targetHeader?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function calculateOfferFinal() {
    var p = parseFloat(document.getElementById('offer_price').value) || 0;
    var d = parseFloat(document.getElementById('offer_discount').value) || 0;
    document.getElementById('offer_final').value = Math.max(0, p - d).toFixed(2);
}
</script>
@endsection
