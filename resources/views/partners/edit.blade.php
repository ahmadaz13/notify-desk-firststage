@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('partners.index') }}">الشركاء</a> / تعديل</div>
        <h1 class="page-title">تعديل بيانات الشريك: {{ $partner->company_name }}</h1>
    </div>
</div>

<div class="card form-card" style="margin:0 auto">
    <form method="POST" action="{{ route('partners.update', $partner->id) }}">
        @csrf
        @method('PUT')

        <div class="field" style="margin-bottom:16px">
            <label>اسم الشركة / الشريك *</label>
            <input class="touch-input" type="text" name="company_name" value="{{ old('company_name', $partner->company_name) }}" required>
            @error('company_name') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>البريد الإلكتروني للجهة / الحساب *</label>
            <input class="touch-input" type="email" name="email" value="{{ old('email', $partner->email) }}" required style="direction:ltr;text-align:right">
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>رقم الهاتف (اختياري)</label>
            <input class="touch-input" type="text" name="phone" value="{{ old('phone', $partner->phone) }}">
            @error('phone') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>نسبة حصة الأرباح % (اختياري)</label>
            <input class="touch-input" type="number" step="0.01" min="0" max="100" name="profit_share_percentage" value="{{ old('profit_share_percentage', $partner->profit_share_percentage) }}">
            @error('profit_share_percentage') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:24px">
            <label>نسبة استقطاع التشغيل % (الافتراضي 20%)</label>
            <input class="touch-input" type="number" step="0.01" min="0" max="100" name="deduction_percentage" value="{{ old('deduction_percentage', $partner->deduction_percentage ?? '20.00') }}">
            <small class="muted">النسبة المستقطعة لتكاليف التشغيل قبل احتساب أرباح الشريك.</small>
            @error('deduction_percentage') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end">
            <a href="{{ route('partners.index') }}" class="btn btn-ghost touch-btn">إلغاء</a>
            <button type="submit" class="btn btn-primary touch-btn">حفظ التعديلات</button>
        </div>
    </form>
</div>

<div class="card form-card" style="margin:20px auto 0;border-top:3px solid var(--nd-warning)">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div>
            <h3 style="margin:0 0 4px;font-size:16px">إعادة تعيين كلمة المرور للشريك</h3>
            <div class="muted">توليد كلمة مرور جديدة فورية للحساب وعرضها لنسخها وإرسالها للشريك.</div>
        </div>
        <form method="POST" action="{{ route('partners.reset-password', $partner->id) }}" onsubmit="return confirm('هل أنت متأكد من إعادة تعيين كلمة مرور هذا الشريك؟ سيتم توليد كلمة مرور جديدة فوراً.')">
            @csrf
            <button type="submit" class="btn btn-danger touch-btn" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d">
                🔑 إعادة تعيين كلمة المرور
            </button>
        </form>
    </div>
</div>
@endsection
