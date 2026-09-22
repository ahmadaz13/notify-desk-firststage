@extends('layouts.app')

@section('content')
<div class="notify-page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.index') }}">{{ __('notify.clients.title') }}</a> / {{ __('notify.clients.create_title') }}</div>
        <h1 class="page-title">{{ __('notify.clients.create_title') }}</h1>
    </div>
</div>

<section class="notify-form-shell">
    <form
        method="POST"
        action="{{ route('clients.store') }}"
        class="notify-prospect-form"
        x-init="$nextTick(() => { const field = $el.querySelector('[aria-invalid=true]'); if (field) { field.focus(); field.scrollIntoView({ block: 'center' }); } })"
    >
        @csrf

        <div class="notify-form-grid">
            <div class="field" data-intake-field="business_name">
                <label for="business_name">{{ __('notify.clients.business_name') }} *</label>
                <input id="business_name" class="touch-input" name="business_name" value="{{ old('business_name') }}" required maxlength="255" autocomplete="organization" @error('business_name') aria-invalid="true" @enderror>
                @error('business_name') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="business_category">
                <label for="business_category">{{ __('notify.clients.business_type') }} *</label>
                <input id="business_category" class="touch-input" name="business_category" value="{{ old('business_category') }}" list="business-category-options" required maxlength="120" @error('business_category') aria-invalid="true" @enderror>
                <datalist id="business-category-options">
                    @foreach($businessCategorySuggestions as $category)
                        <option value="{{ $category }}"></option>
                    @endforeach
                </datalist>
                @error('business_category') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="contact_person">
                <label for="contact_person">{{ __('notify.clients.contact_person') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                <input id="contact_person" class="touch-input" name="contact_person" value="{{ old('contact_person') }}" maxlength="120" autocomplete="name" @error('contact_person') aria-invalid="true" @enderror>
                @error('contact_person') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="phone">
                <label for="phone">{{ __('notify.clients.mobile') }} *</label>
                <input id="phone" class="touch-input" type="tel" name="phone" value="{{ old('phone') }}" required maxlength="50" inputmode="tel" autocomplete="tel" dir="ltr" @error('phone') aria-invalid="true" @enderror>
                @error('phone') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="city_area">
                <label for="city_area">{{ __('notify.clients.area') }} *</label>
                <input id="city_area" class="touch-input" name="city_area" value="{{ old('city_area') }}" required maxlength="120" autocomplete="address-level2" @error('city_area') aria-invalid="true" @enderror>
                @error('city_area') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="lead_source">
                <label for="lead_source">{{ __('notify.clients.lead_source') }} *</label>
                <select id="lead_source" class="touch-input" name="lead_source" required @error('lead_source') aria-invalid="true" @enderror>
                    <option value="">{{ __('notify.clients.choose_lead_source') }}</option>
                    @foreach($leadSourceOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('lead_source') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('lead_source') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="referred_by_name">{{ __('notify.clients.referred_by_name') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                <input id="referred_by_name" class="touch-input" name="referred_by_name" value="{{ old('referred_by_name') }}" maxlength="255">
            </div>
            <div class="field">
                <label for="referral_note">{{ __('notify.clients.referral_note') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                <input id="referral_note" class="touch-input" name="referral_note" value="{{ old('referral_note') }}" maxlength="1000">
            </div>
        </div>

        <div class="notify-form-footer notify-form-footer--sticky">
            <x-notify.button :href="route('clients.index')" variant="ghost">{{ __('notify.actions.cancel') }}</x-notify.button>
            <x-notify.button type="submit" variant="primary" icon="plus">{{ __('notify.clients.create_prospect_action') }}</x-notify.button>
        </div>
    </form>
</section>
@endsection
