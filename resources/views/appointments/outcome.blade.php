@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.show', $item->client_id) }}">{{ $item->business_name }}</a> / مخرجات الموعد</div>
        <h1 class="page-title">تسجيل نتائج الاجتماع</h1>
        <div class="muted" style="margin-top:5px">
            الموعد الأصلي: {{ $item->appointment_date }} · {{ $item->appointment_time }} · {{ $item->location ?: 'عن بُعد' }}
        </div>
    </div>
    <a class="btn btn-ghost" href="{{ route('clients.show', $item->client_id) }}">← إلغاء والعودة لملف العميل</a>
</div>

<div class="card form-card" style="max-width:840px">
    <form method="POST" action="{{ route('appointments.outcome.store', $item->id) }}">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label>حالة الحضور *</label>
                <select name="attendance_status" required>
                    <option value="attended">{{ __('notify.appointments.attended') }}</option>
                    <option value="attended_late">{{ __('notify.appointments.attended_late') }}</option>
                    <option value="no_show">{{ __('notify.appointments.no_show') }}</option>
                    <option value="cancelled">{{ __('notify.appointments.cancelled') }}</option>
                </select>
            </div>

            <div class="field">
                <label>نوع الاجتماع الفعلي *</label>
                <select name="meeting_type" required>
                    <option value="physical_visit" @selected($item->appointment_type === 'physical_visit')>{{ __('notify.appointments.physical_visit') }}</option>
                    <option value="online_demo" @selected($item->appointment_type === 'online_demo')>{{ __('notify.appointments.online_demo') }}</option>
                    <option value="phone_call" @selected($item->appointment_type === 'phone_call')>{{ __('notify.appointments.phone_call') }}</option>
                </select>
            </div>

            <div class="field">
                <label>مستوى اهتمام العميل *</label>
                <select name="interest_level" required>
                    <option value="high">{{ __('notify.appointments.hot') }}</option>
                    <option value="medium" selected>{{ __('notify.appointments.warm') }}</option>
                    <option value="low">{{ __('notify.appointments.cold') }}</option>
                    <option value="none">{{ __('notify.appointments.not_interested') }}</option>
                </select>
            </div>

            <div class="field">
                <label>{{ __('notify.appointments.systems_discussed') }}</label>
                <input name="package_discussed" placeholder="{{ __('notify.appointments.systems_discussed_placeholder') }}">
            </div>

            <div class="field full" style="display:flex;flex-direction:row;gap:24px;padding:8px 0">
                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="demo_performed" value="1" checked>
                    <span>{{ __('notify.appointments.demo_performed') }}</span>
                </label>
                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="price_discussed" value="1" checked>
                    <span>{{ __('notify.appointments.price_discussed') }}</span>
                </label>
            </div>

            <div class="field">
                <label>احتياجات العميل الأساسية</label>
                <textarea name="customer_needs" placeholder="ما هي النقاط التي يحتاجها العميل في نشاطه؟"></textarea>
            </div>

            <div class="field">
                <label>أبرز الاعتراضات أو التحديات</label>
                <textarea name="main_objections" placeholder="مثل: الميزانية، النظام الحالي، وقت التدريب..."></textarea>
            </div>

            <div class="field full">
                <label>{{ __('notify.appointments.feedback_response') }}</label>
                <textarea name="customer_response" placeholder="ماذا قال العميل في نهاية الجلسة؟"></textarea>
            </div>

            <div class="field">
                <label>الخطوة التالية المحددة *</label>
                <input name="next_action" required placeholder="مثال: إرسال عرض تجاري مخفض، مكالمة هاتفية...">
            </div>

            <div class="field">
                <label>تاريخ المتابعة القادمة</label>
                <input type="date" name="next_follow_up_date" value="{{ now()->addDays(2)->toDateString() }}">
            </div>

            <div class="field full">
                <label>ملاحظات إضافية</label>
                <textarea name="meeting_notes" placeholder="أي تفاصيل أخرى خاصة بالاجتماع..."></textarea>
            </div>

            <div class="field full" style="margin-top:10px">
                <button class="btn btn-primary" style="padding:14px 22px;font-size:14px" type="submit">
                    ✓ حفظ مخرجات الاجتماع وإكمال الموعد
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
