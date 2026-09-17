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
            <label>اسم جهة الاتصال</label>
            <input class="touch-input" name="contact_name" value="{{ old('contact_name', $partner->contact_name) }}">
            @error('contact_name') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>البريد الإلكتروني للجهة *</label>
            <input class="touch-input" type="email" name="email" value="{{ old('email', $partner->email) }}" required style="direction:ltr;text-align:right">
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>رقم الهاتف (اختياري)</label>
            <input class="touch-input" type="text" name="phone" value="{{ old('phone', $partner->phone) }}">
            @error('phone') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>نسبة العمولة الافتراضية %</label>
            <input class="touch-input" type="number" step="0.01" min="0" max="100" name="default_commission_percentage" value="{{ old('default_commission_percentage', $partner->effectiveDefaultCommissionBps() === null ? '' : number_format($partner->effectiveDefaultCommissionBps() / 100, 2, '.', '')) }}">
            <small class="muted">تغييرها لا يعدّل نسب العملاء المسندين سابقاً.</small>
            @error('default_commission_percentage') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="margin-bottom:16px">
            <label>الحالة</label>
            <select class="touch-input" name="status">
                <option value="active" @selected(old('status', $partner->status) === 'active')>نشط</option>
                <option value="suspended" @selected(old('status', $partner->status) === 'suspended')>مؤرشف</option>
            </select>
        </div>

        <div class="field" style="margin-bottom:24px">
            <label>ملاحظات</label>
            <textarea name="notes">{{ old('notes', $partner->notes) }}</textarea>
            @error('notes') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end">
            <a href="{{ route('partners.index') }}" class="btn btn-ghost touch-btn">إلغاء</a>
            <button type="submit" class="btn btn-primary touch-btn">حفظ التعديلات</button>
        </div>
    </form>
</div>

@endsection
