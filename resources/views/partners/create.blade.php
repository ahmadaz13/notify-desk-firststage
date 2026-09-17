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
            <label>اسم جهة الاتصال</label>
            <input class="touch-input" name="contact_name" value="{{ old('contact_name') }}">
            @error('contact_name') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>البريد الإلكتروني للجهة *</label>
            <input class="touch-input" type="email" name="email" value="{{ old('email') }}" required placeholder="partner@example.com" style="direction:ltr;text-align:right">
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>رقم الهاتف (اختياري)</label>
            <input class="touch-input" type="text" name="phone" value="{{ old('phone') }}" placeholder="0791234567">
            @error('phone') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>نسبة العمولة الافتراضية %</label>
            <input class="touch-input" type="number" step="0.01" min="0" max="100" name="default_commission_percentage" value="{{ old('default_commission_percentage') }}" placeholder="مثال: 15.00">
            <small class="muted">تُنسخ هذه النسبة عند إسناد العميل ولا تتغير تلقائياً لاحقاً.</small>
            @error('default_commission_percentage') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:24px">
            <label>ملاحظات</label>
            <textarea name="notes">{{ old('notes') }}</textarea>
            @error('notes') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end">
            <a href="{{ route('partners.index') }}" class="btn btn-ghost touch-btn">إلغاء</a>
            <button type="submit" class="btn btn-primary touch-btn">حفظ مرجع الشريك</button>
        </div>
    </form>
</div>
@endsection
