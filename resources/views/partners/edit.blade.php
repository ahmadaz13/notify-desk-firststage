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

        <div class="field" style="margin-bottom:24px">
            <label>نسبة حصة الأرباح % (اختياري)</label>
            <input class="touch-input" type="number" step="0.01" min="0" max="100" name="profit_share_percentage" value="{{ old('profit_share_percentage', $partner->profit_share_percentage) }}">
            @error('profit_share_percentage') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end">
            <a href="{{ route('partners.index') }}" class="btn btn-ghost touch-btn">إلغاء</a>
            <button type="submit" class="btn btn-primary touch-btn">حفظ التعديلات</button>
        </div>
    </form>
</div>
@endsection
