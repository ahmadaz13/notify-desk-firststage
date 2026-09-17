@extends('layouts.app')

@section('content')
<div class="notify-page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.index') }}">{{ __('notify.clients.title') }}</a> / {{ __('notify.clients.create_title') }}</div>
        <h1 class="page-title">{{ __('notify.clients.create_title') }}</h1>
        <p class="notify-page-lede">{{ __('notify.clients.create_lede') }}</p>
    </div>
    <x-notify.button :href="route('clients.index')" variant="ghost">{{ __('notify.actions.cancel') }}</x-notify.button>
</div>

<section class="notify-form-shell">
    <form method="POST" action="{{ route('clients.store') }}" class="notify-prospect-form">
        @csrf

        <div class="notify-form-section">
            <div class="notify-section-title notify-section-title--compact">
                <div>
                    <h2>{{ __('notify.clients.section_business') }}</h2>
                    <p>{{ __('notify.clients.section_business_meta') }}</p>
                </div>
            </div>
            <div class="notify-form-grid">
                <div class="field">
                    <label for="business_name">{{ __('notify.clients.business_name') }} *</label>
                    <input id="business_name" class="touch-input" name="business_name" value="{{ old('business_name') }}" required autocomplete="organization">
                    @error('business_name') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="business_category">{{ __('notify.clients.business_type') }} *</label>
                    <input id="business_category" class="touch-input" name="business_category" value="{{ old('business_category') }}" required>
                    @error('business_category') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="business_phone">هاتف النشاط التجاري (للاستخدام كخيار احتياطي) *</label>
                    <input id="business_phone" class="touch-input" name="business_phone" value="{{ old('business_phone') }}" required inputmode="tel" autocomplete="tel" dir="ltr">
                    @error('business_phone') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="city_area">{{ __('notify.clients.city_area') }} *</label>
                    <input id="city_area" class="touch-input" name="city_area" value="{{ old('city_area') }}" required>
                    @error('city_area') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="notify-form-section">
            <div class="notify-section-title notify-section-title--compact">
                <div>
                    <h2>{{ __('notify.clients.section_contact') }}</h2>
                    <p>{{ __('notify.clients.section_contact_meta') }}</p>
                </div>
            </div>
            <div class="notify-form-grid">
                <div class="field">
                    <label for="contact_person">اسم جهة الاتصال الأساسية (المالك أو صاحب القرار مفضّل) *</label>
                    <input id="contact_person" class="touch-input" name="contact_person" value="{{ old('contact_person') }}" required autocomplete="name">
                    @error('contact_person') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="phone">رقم جوال جهة الاتصال الأساسية *</label>
                    <input id="phone" class="touch-input" name="phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" dir="ltr">
                    @error('phone') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="primary_contact_role">صفة جهة الاتصال الأساسية *</label>
                    <select id="primary_contact_role" class="touch-input" name="primary_contact_role" required>
                        <option value="owner" @selected(old('primary_contact_role', 'owner') === 'owner')>مالك / صاحب قرار</option>
                        <option value="manager" @selected(old('primary_contact_role') === 'manager')>مدير</option>
                        <option value="other" @selected(old('primary_contact_role') === 'other')>جهة اتصال أخرى</option>
                    </select>
                    @error('primary_contact_role') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="notify-form-section">
            <div class="notify-section-title notify-section-title--compact">
                <div>
                    <h2>{{ __('notify.clients.section_source') }}</h2>
                    <p>{{ __('notify.clients.section_source_meta') }}</p>
                </div>
            </div>
            <div class="notify-form-grid">
                <div class="field">
                    <label for="lead_source">{{ __('notify.clients.lead_source') }} *</label>
                    <select id="lead_source" class="touch-input" name="lead_source" required>
                        @foreach(['Google Maps', 'Instagram', 'Referral', 'Direct Prospecting', 'Partner', 'Other'] as $source)
                            <option value="{{ $source }}" @selected(old('lead_source', 'Google Maps') === $source)>{{ $source }}</option>
                        @endforeach
                    </select>
                    @error('lead_source') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="source_reference">{{ __('notify.clients.source_reference') }}</label>
                    <input id="source_reference" class="touch-input" name="source_reference" value="{{ old('source_reference') }}">
                    @error('source_reference') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field full">
                    <label for="partner_id">{{ __('notify.clients.partner_attribution') }}</label>
                    <select id="partner_id" class="touch-input" name="partner_id">
                        <option value="">{{ __('notify.clients.no_partner_attribution') }}</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}" @selected(old('partner_id') == $partner->id)>
                                {{ $partner->company_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('partner_id') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="partner_commission_percentage">نسبة العمولة المتفق عليها %</label>
                    <input id="partner_commission_percentage" class="touch-input" type="number" step="0.01" min="0" max="100" name="partner_commission_percentage" value="{{ old('partner_commission_percentage') }}" placeholder="اتركها فارغة لاستخدام نسبة الشريك الافتراضية">
                    @error('partner_commission_percentage') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="partner_attribution_notes">ملاحظات اتفاق الإحالة</label>
                    <input id="partner_attribution_notes" class="touch-input" name="partner_attribution_notes" value="{{ old('partner_attribution_notes') }}">
                    @error('partner_attribution_notes') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="notify-form-section">
            <div class="notify-section-title notify-section-title--compact">
                <div>
                    <h2>{{ __('notify.clients.section_notes') }}</h2>
                    <p>{{ __('notify.clients.section_notes_meta') }}</p>
                </div>
            </div>
            <div class="notify-form-grid">
                <div class="field">
                    <label for="city">{{ __('notify.clients.city') }}</label>
                    <input id="city" class="touch-input" name="city" value="{{ old('city') }}">
                    @error('city') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="area">{{ __('notify.clients.area') }}</label>
                    <input id="area" class="touch-input" name="area" value="{{ old('area') }}">
                    @error('area') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field full">
                    <label for="notes">{{ __('notify.clients.notes') }}</label>
                    <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
                    @error('notes') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="notify-form-footer">
            <x-notify.button :href="route('clients.index')" variant="ghost">{{ __('notify.actions.cancel') }}</x-notify.button>
            <x-notify.button type="submit" variant="primary" icon="plus">{{ __('notify.clients.create_prospect_action') }}</x-notify.button>
        </div>
    </form>
</section>
@endsection
