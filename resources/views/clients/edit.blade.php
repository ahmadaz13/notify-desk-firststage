@extends('layouts.app')

@section('content')
@php
    $businessPhone = old('business_phone', $client->business_phone && $client->business_phone !== $client->phone ? $client->business_phone : '');
    $primaryContactName = $client->primaryContact?->name ?: $client->contact_person;
    $primaryContactRole = old('primary_contact_role', $client->primaryContact?->role ?: 'owner');
    $detailFields = ['primary_contact_role', 'source_reference', 'number_of_branches', 'city', 'area', 'instagram', 'website', 'maps_url', 'location_text', 'notes'];
@endphp

<div class="notify-page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.show', $client->id) }}">{{ $client->business_name }}</a> / {{ __('notify.clients.edit_title') }}</div>
        <h1 class="page-title">{{ __('notify.clients.edit_title') }}</h1>
    </div>
</div>

<section class="notify-form-shell">
    <form
        method="POST"
        action="{{ route('clients.update', $client->id) }}"
        class="notify-prospect-form"
        x-init="$nextTick(() => { const field = $el.querySelector('[aria-invalid=true]'); if (field) { field.focus(); field.scrollIntoView({ block: 'center' }); } })"
    >
        @csrf
        @method('PUT')

        <div class="notify-form-section">
            <div class="notify-section-title notify-section-title--compact">
                <div>
                    <h2>{{ __('notify.clients.basic_details') }}</h2>
                    <p>{{ __('notify.clients.basic_details_meta') }}</p>
                </div>
            </div>

            <div class="notify-form-grid">
                <div class="field">
                    <label for="business_name">{{ __('notify.clients.business_name') }} *</label>
                    <input id="business_name" class="touch-input" name="business_name" value="{{ old('business_name', $client->business_name) }}" required maxlength="255" autocomplete="organization" @error('business_name') aria-invalid="true" @enderror>
                    @error('business_name') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="business_category">{{ __('notify.clients.business_type') }} *</label>
                    <input id="business_category" class="touch-input" name="business_category" value="{{ old('business_category', $client->business_category) }}" list="business-category-options" required maxlength="120" @error('business_category') aria-invalid="true" @enderror>
                    <datalist id="business-category-options">
                        @foreach($businessCategorySuggestions as $category)
                            <option value="{{ $category }}"></option>
                        @endforeach
                    </datalist>
                    @error('business_category') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="contact_person">{{ __('notify.clients.contact_person') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                    <input id="contact_person" class="touch-input" name="contact_person" value="{{ old('contact_person', $primaryContactName) }}" maxlength="120" autocomplete="name" @error('contact_person') aria-invalid="true" @enderror>
                    @error('contact_person') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="phone">{{ __('notify.clients.mobile') }} *</label>
                    <input id="phone" class="touch-input" type="tel" name="phone" value="{{ old('phone', $client->phone) }}" required maxlength="50" inputmode="tel" autocomplete="tel" dir="ltr" @error('phone') aria-invalid="true" @enderror>
                    @error('phone') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="business_phone">{{ __('notify.clients.business_phone_if_different') }}</label>
                    <input id="business_phone" class="touch-input" type="tel" name="business_phone" value="{{ $businessPhone }}" maxlength="50" inputmode="tel" autocomplete="tel" dir="ltr" @error('business_phone') aria-invalid="true" @enderror>
                    @error('business_phone') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="city_area">{{ __('notify.clients.area') }} *</label>
                    <input id="city_area" class="touch-input" name="city_area" value="{{ old('city_area', $client->city_area) }}" required maxlength="120" autocomplete="address-level2" @error('city_area') aria-invalid="true" @enderror>
                    @error('city_area') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="lead_source">{{ __('notify.clients.lead_source') }} *</label>
                    <select id="lead_source" class="touch-input" name="lead_source" required @error('lead_source') aria-invalid="true" @enderror>
                        <option value="">{{ __('notify.clients.choose_lead_source') }}</option>
                        @foreach($leadSourceOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('lead_source', $client->lead_source) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('lead_source') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="referred_by_name">{{ __('notify.clients.referred_by_name') }}</label>
                    <input id="referred_by_name" class="touch-input" name="referred_by_name" value="{{ old('referred_by_name', $client->referred_by_name) }}" maxlength="255">
                </div>
                <div class="field">
                    <label for="referral_note">{{ __('notify.clients.referral_note') }}</label>
                    <input id="referral_note" class="touch-input" name="referral_note" value="{{ old('referral_note', $client->referral_note) }}" maxlength="1000">
                </div>
                @if(auth()->user()->isAdmin())
                <div class="field">
                    <label for="referral_commission_percentage">{{ __('notify.clients.referral_commission_percentage') }}</label>
                    <input id="referral_commission_percentage" class="touch-input" name="referral_commission_percentage" type="number" min="0" max="100" step="0.01" value="{{ old('referral_commission_percentage', $client->referral_commission_bps === null ? '' : $client->referral_commission_bps / 100) }}">
                </div>
                @endif
                </div>
            </div>
        </div>

        <details class="notify-details" @if($errors->hasAny($detailFields)) open @endif>
            <summary>
                <span>{{ __('notify.clients.more_details') }}</span>
                <small>{{ __('notify.clients.more_details_meta') }}</small>
            </summary>
            <div class="notify-form-grid notify-details__body">
                <div class="field">
                    <label for="primary_contact_role">{{ __('notify.clients.primary_contact_role') }}</label>
                    <select id="primary_contact_role" class="touch-input" name="primary_contact_role">
                        <option value="owner" @selected($primaryContactRole === 'owner')>{{ __('notify.clients.contact_roles.owner') }}</option>
                        <option value="manager" @selected($primaryContactRole === 'manager')>{{ __('notify.clients.contact_roles.manager') }}</option>
                        <option value="other" @selected($primaryContactRole === 'other')>{{ __('notify.clients.contact_roles.other') }}</option>
                    </select>
                    @error('primary_contact_role') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="source_reference">{{ __('notify.clients.source_reference') }}</label>
                    <input id="source_reference" class="touch-input" name="source_reference" value="{{ old('source_reference', $client->source_reference) }}" maxlength="255" @error('source_reference') aria-invalid="true" @enderror>
                    @error('source_reference') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="number_of_branches">{{ __('notify.clients.number_of_branches') }}</label>
                    <input id="number_of_branches" class="touch-input" type="number" min="1" max="999" name="number_of_branches" value="{{ old('number_of_branches', $client->number_of_branches ?? 1) }}" @error('number_of_branches') aria-invalid="true" @enderror>
                    @error('number_of_branches') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="city">{{ __('notify.clients.city') }}</label>
                    <input id="city" class="touch-input" name="city" value="{{ old('city', $client->city) }}" maxlength="120" @error('city') aria-invalid="true" @enderror>
                    @error('city') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="area">{{ __('notify.clients.area') }}</label>
                    <input id="area" class="touch-input" name="area" value="{{ old('area', $client->area) }}" maxlength="120" @error('area') aria-invalid="true" @enderror>
                    @error('area') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="instagram">Instagram</label>
                    <input id="instagram" class="touch-input" name="instagram" value="{{ old('instagram', $client->instagram) }}" maxlength="255" dir="ltr" @error('instagram') aria-invalid="true" @enderror>
                    @error('instagram') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="website">{{ __('notify.clients.website') }}</label>
                    <input id="website" class="touch-input" type="url" name="website" value="{{ old('website', $client->website) }}" maxlength="255" dir="ltr" @error('website') aria-invalid="true" @enderror>
                    @error('website') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="maps_url">{{ __('notify.clients.maps_url') }}</label>
                    <input id="maps_url" class="touch-input" type="url" name="maps_url" value="{{ old('maps_url', $client->maps_url) }}" maxlength="500" dir="ltr" @error('maps_url') aria-invalid="true" @enderror>
                    @error('maps_url') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field full">
                    <label for="location_text">{{ __('notify.clients.location_text') }}</label>
                    <input id="location_text" class="touch-input" name="location_text" value="{{ old('location_text', $client->location_text) }}" maxlength="255" @error('location_text') aria-invalid="true" @enderror>
                    @error('location_text') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field full">
                    <label for="notes">{{ __('notify.clients.notes') }}</label>
                    <textarea id="notes" name="notes" @error('notes') aria-invalid="true" @enderror>{{ old('notes', $client->notes) }}</textarea>
                    @error('notes') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>
            </div>
        </details>

        <div class="notify-form-footer notify-form-footer--sticky">
            <x-notify.button :href="route('clients.show', $client->id)" variant="ghost">{{ __('notify.actions.cancel') }}</x-notify.button>
            <x-notify.button type="submit" variant="primary">{{ __('notify.clients.save_changes') }}</x-notify.button>
        </div>
    </form>
</section>
@endsection
