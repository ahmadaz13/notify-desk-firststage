@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('conflicts.index') }}">طلبات التعارض</a> / مراجعة #{{ $conflict->id }}</div>
        <h1 class="page-title">مقارنة بيانات التعارض والبت فيه</h1>
    </div>
</div>

<div class="card" style="margin-bottom:20px;background:#f8fafc">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div>
            <span class="badge gold" style="font-size:12px;padding:6px 12px">حالة الطلب: قيد المراجعة</span>
            <span class="muted" style="margin-right:10px">تاريخ الورود: {{ $conflict->created_at->format('Y-m-d H:i') }}</span>
        </div>
        <div>
            <span class="muted">الشريك مقدم الطلب:</span>
            <strong style="color:var(--nd-accent)">{{ $conflict->partner?->company_name }}</strong>
        </div>
    </div>
</div>

<div class="grid grid-2" style="gap:20px;margin-bottom:24px">
    {{-- Old Data (Current Client in System) --}}
    <div class="card" style="border:1.5px solid var(--nd-border)">
        <div style="border-bottom:1px solid var(--nd-border);padding-bottom:12px;margin-bottom:16px">
            <h3 style="margin:0;font-size:17px;color:var(--nd-ink)">📌 {{ __('notify.conflicts.current_system_data') }}</h3>
            <div class="muted">البيانات المسجلة مسبقاً لهذا الرقم</div>
        </div>

        @if($conflict->client)
            <div style="display:flex;flex-direction:column;gap:12px;font-size:14px">
                <div>
                    <span class="muted" style="display:block;font-size:12px">اسم العميل / المنشأة:</span>
                    <strong style="font-size:16px">{{ $conflict->client->business_name }}</strong>
                </div>
                <div>
                    <span class="muted" style="display:block;font-size:12px">رقم الهاتف:</span>
                    <span style="font-family:monospace;direction:ltr;display:inline-block">{{ $conflict->client->phone }}</span>
                </div>
                <div>
                    <span class="muted" style="display:block;font-size:12px">المنطقة:</span>
                    <span>{{ $conflict->client->city_area }}</span>
                </div>
                <div>
                    <span class="muted" style="display:block;font-size:12px">المصدر:</span>
                    <span>{{ $conflict->client->lead_source }}</span>
                </div>
                <div>
                    <span class="muted" style="display:block;font-size:12px">الشريك الحالي:</span>
                    <span>{{ $conflict->client->partner ? $conflict->client->partner->company_name : 'مباشر للمكتب' }}</span>
                </div>
                <div>
                    <span class="muted" style="display:block;font-size:12px">الحالة الحالية:</span>
                    <span class="badge {{ $conflict->client->status === 'subscriber' ? 'green' : 'gold' }}">{{ $conflict->client->status }}</span>
                </div>
            </div>
        @else
            <div class="muted">العميل المرتبط بهذا السجل غير موجود حالياً.</div>
        @endif
    </div>

    {{-- New Data (Submitted by Partner Delegate) --}}
    <div class="card" style="border:1.5px solid var(--nd-accent);background:#fbfefe">
        <div style="border-bottom:1px solid rgba(15,118,110,.2);padding-bottom:12px;margin-bottom:16px">
            <h3 style="margin:0;font-size:17px;color:var(--nd-accent)">✨ {{ __('notify.conflicts.new_partner_data') }}</h3>
            <div class="muted">أدخلت بواسطة مندوب {{ $conflict->partner?->company_name }}</div>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;font-size:14px">
            <div>
                <span class="muted" style="display:block;font-size:12px">الاسم المقترح من الشريك:</span>
                <strong style="font-size:16px;color:var(--nd-accent)">{{ $conflict->submitted_name }}</strong>
            </div>
            <div>
                <span class="muted" style="display:block;font-size:12px">رقم الهاتف المدخل:</span>
                <span style="font-family:monospace;direction:ltr;display:inline-block">{{ $conflict->submitted_phone }}</span>
            </div>
            <div>
                <span class="muted" style="display:block;font-size:12px">المنطقة المقترحة:</span>
                <span>{{ $conflict->submitted_area ?: '—' }}</span>
            </div>
            <div>
                <span class="muted" style="display:block;font-size:12px">المصدر المدخل:</span>
                <span>{{ $conflict->submitted_source ?: '—' }}</span>
            </div>
            <div>
                <span class="muted" style="display:block;font-size:12px">الشريك المطالب بالتبعية:</span>
                <strong style="color:var(--nd-accent)">{{ $conflict->partner?->company_name }}</strong>
            </div>
        </div>
    </div>
</div>

{{-- Decision Action Form --}}
<div class="card" style="padding:24px;border:2px dashed var(--nd-border)">
    <h3 style="margin:0 0 8px;font-size:18px">اتخاذ القرار النهائي بشأن هذا التعارض</h3>
    <p class="muted" style="margin:0 0 20px">اختر الإجراء المناسب بناءً على أحقية الشريك أو رغبة الإدارة:</p>

    <form method="POST" action="{{ route('conflicts.resolve', $conflict->id) }}" style="display:flex;gap:14px;flex-wrap:wrap">
        @csrf
        
        {{-- Transfer Button --}}
        <button type="submit" name="action" value="transfer" class="btn btn-primary touch-btn" style="min-height:48px;font-size:14px" onclick="return confirm('تأكيد: سيتم نقل تبعية العميل إلى الشريك مع تحديث البيانات.')">
            🤝 {{ __('notify.conflicts.transfer_client') }}
        </button>

        {{-- Update Only Button --}}
        <button type="submit" name="action" value="update" class="btn btn-soft touch-btn" style="min-height:48px;font-size:14px" onclick="return confirm('تأكيد: سيتم تحديث بيانات العميل فقط دون نقل الشريك.')">
            ✏️ {{ __('notify.conflicts.update_only') }}
        </button>

        {{-- Reject Button --}}
        <button type="submit" name="action" value="reject" class="btn btn-danger touch-btn" style="min-height:48px;font-size:14px" onclick="return confirm('تأكيد: هل أنت متأكد من رفض هذا الطلب؟')">
            🚫 {{ __('notify.conflicts.reject') }}
        </button>
    </form>
</div>
@endsection
