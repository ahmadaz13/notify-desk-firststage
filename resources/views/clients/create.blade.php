@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.index') }}">العملاء</a> / جديد</div>
        <h1 class="page-title">إضافة عميل جديد</h1>
    </div>
</div>

<div class="card form-card" style="margin:0 auto">
    <form method="POST" action="{{ route('clients.store') }}">
        @csrf
        
        <div class="form-grid">
            <div class="field">
                <label>اسم النشاط التجاري / العميل *</label>
                <input class="touch-input" name="business_name" value="{{ old('business_name') }}" required placeholder="مخبز، صالون، مطعم...">
                @error('business_name') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>رقم الهاتف *</label>
                <input class="touch-input" name="phone" value="{{ old('phone') }}" required placeholder="079XXXXXXXX" style="direction:ltr;text-align:right">
                @error('phone') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>المنطقة / المدينة *</label>
                <input class="touch-input" name="city_area" value="{{ old('city_area') }}" required placeholder="عمان - عبدون">
                @error('city_area') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>التصنيف التجاري *</label>
                <input class="touch-input" name="business_category" value="{{ old('business_category') }}" required placeholder="مطاعم، عيادات، أزياء...">
                @error('business_category') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>مصدر العميل *</label>
                <select class="touch-input" name="lead_source">
                    <option value="Google Maps" {{ old('lead_source') === 'Google Maps' ? 'selected' : '' }}>Google Maps</option>
                    <option value="Instagram" {{ old('lead_source') === 'Instagram' ? 'selected' : '' }}>Instagram</option>
                    <option value="Referral" {{ old('lead_source') === 'Referral' ? 'selected' : '' }}>Referral (ترشيح)</option>
                    <option value="Direct Prospecting" {{ old('lead_source') === 'Direct Prospecting' ? 'selected' : '' }}>Direct Prospecting (زيارة ميدانية)</option>
                    <option value="Partner" {{ old('lead_source') === 'Partner' ? 'selected' : '' }}>Partner (شريك)</option>
                    <option value="Other" {{ old('lead_source') === 'Other' ? 'selected' : '' }}>Other (أخرى)</option>
                </select>
                @error('lead_source') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>جهة الاتصال (اختياري)</label>
                <input class="touch-input" name="contact_person" value="{{ old('contact_person') }}" placeholder="اسم المدير أو المسؤول">
                @error('contact_person') <span class="error">{{ $message }}</span> @enderror
            </div>

            @if(auth()->user()->isAdmin())
                <div class="field full">
                    <label>الشريك التابع له العميل (اختياري)</label>
                    <select class="touch-input" name="partner_id">
                        <option value="">لا يوجد (مباشر للمكتب)</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}" {{ old('partner_id') == $partner->id ? 'selected' : '' }}>
                                {{ $partner->company_name }} ({{ $partner->email }})
                            </option>
                        @endforeach
                    </select>
                    <small class="muted">الشركاء لا يمكنهم رؤية هذا الخيار، ويتم ربط عملائهم تلقائياً بحسابهم.</small>
                    @error('partner_id') <span class="error">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="field full">
                <label>ملاحظات (اختياري)</label>
                <textarea name="notes" placeholder="أي تفاصيل عن العميل واحتياجاته">{{ old('notes') }}</textarea>
                @error('notes') <span class="error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px">
            <a href="{{ route('clients.index') }}" class="btn btn-ghost touch-btn">إلغاء</a>
            <button type="submit" class="btn btn-primary touch-btn">حفظ العميل</button>
        </div>
    </form>
</div>
@endsection
