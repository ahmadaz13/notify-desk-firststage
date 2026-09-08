@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.show', $client->id) }}">{{ $client->business_name }}</a> / تعديل</div>
        <h1 class="page-title">تعديل بيانات العميل</h1>
    </div>
</div>

<div class="card form-card" style="margin:0 auto">
    <form method="POST" action="{{ route('clients.update', $client->id) }}">
        @csrf
        @method('PUT')
        
        <div class="form-grid">
            <div class="field">
                <label>اسم النشاط التجاري / العميل *</label>
                <input class="touch-input" name="business_name" value="{{ old('business_name', $client->business_name) }}" required>
                @error('business_name') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>رقم الهاتف *</label>
                <input class="touch-input" name="phone" value="{{ old('phone', $client->phone) }}" required style="direction:ltr;text-align:right">
                @error('phone') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>المنطقة / المدينة *</label>
                <input class="touch-input" name="city_area" value="{{ old('city_area', $client->city_area) }}" required>
                @error('city_area') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>التصنيف التجاري *</label>
                <input class="touch-input" name="business_category" value="{{ old('business_category', $client->business_category) }}" required>
                @error('business_category') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>مصدر العميل *</label>
                <select class="touch-input" name="lead_source">
                    @foreach(['Google Maps', 'Instagram', 'Referral', 'Direct Prospecting', 'Partner', 'Other'] as $src)
                        <option value="{{ $src }}" {{ old('lead_source', $client->lead_source) === $src ? 'selected' : '' }}>{{ $src }}</option>
                    @endforeach
                </select>
                @error('lead_source') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>جهة الاتصال (اختياري)</label>
                <input class="touch-input" name="contact_person" value="{{ old('contact_person', $client->contact_person) }}">
                @error('contact_person') <span class="error">{{ $message }}</span> @enderror
            </div>

            @if(auth()->user()->isAdmin())
                <div class="field full">
                    <label>الشريك التابع له العميل (اختياري)</label>
                    <select class="touch-input" name="partner_id">
                        <option value="">لا يوجد (مباشر للمكتب)</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}" {{ old('partner_id', $client->partner_id) == $partner->id ? 'selected' : '' }}>
                                {{ $partner->company_name }} ({{ $partner->email }})
                            </option>
                        @endforeach
                    </select>
                    @error('partner_id') <span class="error">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="field full">
                <label>ملاحظات (اختياري)</label>
                <textarea name="notes">{{ old('notes', $client->notes) }}</textarea>
                @error('notes') <span class="error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px">
            <a href="{{ route('clients.show', $client->id) }}" class="btn btn-ghost touch-btn">إلغاء</a>
            <button type="submit" class="btn btn-primary touch-btn">حفظ التعديلات</button>
        </div>
    </form>
</div>
@endsection
