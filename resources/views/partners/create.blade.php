@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('partners.index') }}">الشركاء</a> / جديد</div>
        <h1 class="page-title">إضافة شريك جديد</h1>
    </div>
</div>

<div class="card form-card" style="margin:0 auto">
    <form method="POST" action="{{ route('partners.store') }}">
        @csrf
        <div class="field" style="margin-bottom:16px">
            <label>اسم الشركة / الشريك *</label>
            <input class="touch-input" type="text" name="company_name" value="{{ old('company_name') }}" required placeholder="مثال: شركة آفاق التسويق">
            @error('company_name') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>البريد الإلكتروني للجهة / الحساب *</label>
            <input class="touch-input" type="email" name="email" value="{{ old('email') }}" required placeholder="partner@example.com" style="direction:ltr;text-align:right">
            <small class="muted">سيتم إنشاء حساب مستخدم تلقائي للشريك بهذا البريد الإلكتروني مع كلمة مرور أولية.</small>
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>كلمة المرور الأولية للحساب (اختياري)</label>
            <input class="touch-input" type="text" name="password" value="{{ old('password') }}" placeholder="اتركه فارغاً لتوليد كلمة مرور عشوائية تلقائياً" style="direction:ltr;text-align:right">
            <small class="muted">سيتم عرض كلمة المرور مرة واحدة بعد الإنشاء لنسخها وإرسالها للشريك.</small>
            @error('password') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>رقم الهاتف (اختياري)</label>
            <input class="touch-input" type="text" name="phone" value="{{ old('phone') }}" placeholder="0791234567">
            @error('phone') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>نسبة حصة الأرباح % (اختياري)</label>
            <input class="touch-input" type="number" step="0.01" min="0" max="100" name="profit_share_percentage" value="{{ old('profit_share_percentage') }}" placeholder="مثال: 15.00">
            <small class="muted">نسبة الشريك من صافي الإيراد بعد استقطاع تكاليف التشغيل.</small>
            @error('profit_share_percentage') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:24px">
            <label>نسبة استقطاع التشغيل % (الافتراضي 20%)</label>
            <input class="touch-input" type="number" step="0.01" min="0" max="100" name="deduction_percentage" value="{{ old('deduction_percentage', '20.00') }}" placeholder="20.00">
            <small class="muted">النسبة المستقطعة لتكاليف التشغيل قبل احتساب أرباح الشريك.</small>
            @error('deduction_percentage') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end">
            <a href="{{ route('partners.index') }}" class="btn btn-ghost touch-btn">إلغاء</a>
            <button type="submit" class="btn btn-primary touch-btn">حفظ وتوليد بيانات الدخول</button>
        </div>
    </form>
</div>
@endsection
