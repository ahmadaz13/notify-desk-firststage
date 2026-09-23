{{--
    Business vs contact model (§28.1) shared by create and edit.
    Expects: $client (nullable), $leadSourceOptions, $categoryOptions, $businessCategorySuggestions,
    $cityAreaSuggestions, $otherCategoryValue.
--}}
@php
    $client ??= null;
    $primary = $client?->primaryContact;
    $phoneType = old('primary_phone_type', $client?->primary_phone_type ?: ($client ? 'business' : ''));
    $storedPhoneType = $client?->primary_phone_type ?: 'business';
    $storedCategory = $client?->business_category;
    $category = old('business_category', $storedCategory);
    $contactRole = old('contact_role', $primary?->role);
    $contactRoleOptions = [
        'owner' => __('notify.clients.contact_roles.owner'),
        'manager' => __('notify.clients.contact_roles.manager'),
        'other' => __('notify.clients.contact_roles.other'),
    ];
    if (filled($contactRole) && ! array_key_exists($contactRole, $contactRoleOptions)) {
        $contactRoleOptions[$contactRole] = $contactRole;
    }
    // The contact's own number: their primary phone when the client's primary phone is the business,
    // otherwise their second number (their primary phone *is* the client's primary phone).
    $contactPhone = old('contact_phone', $storedPhoneType === 'business' ? $primary?->primary_phone : $primary?->secondary_phone);
    $businessPhone = old('business_phone', $client && $client->business_phone !== $client->phone ? $client->business_phone : '');
@endphp

<div class="notify-client-form__body" x-data="{ phoneType: @js($phoneType), category: @js($category) }">
    <section class="notify-form-section notify-client-form__section" aria-labelledby="client-business-heading">
        <div class="notify-section-title notify-section-title--compact">
            <div>
                <h2 id="client-business-heading">{{ __('notify.clients.contact_model.business_section') }}</h2>
                <p>{{ __('notify.clients.contact_model.business_section_meta') }}</p>
            </div>
        </div>

        <div class="notify-form-grid">
            <div class="field" data-intake-field="business_name">
                <label for="business_name">{{ __('notify.clients.business_name') }} *</label>
                <input id="business_name" class="touch-input" name="business_name" value="{{ old('business_name', $client?->business_name) }}" required maxlength="255" autocomplete="organization" @error('business_name') aria-invalid="true" @enderror>
                @error('business_name') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="business_category">
                <label for="business_category">{{ __('notify.clients.business_type') }} *</label>
                @if($categoryOptions !== [])
                    <select id="business_category" class="touch-input" name="business_category" x-model="category" required @error('business_category') aria-invalid="true" @enderror>
                        <option value="">{{ __('notify.clients.contact_model.category_choose') }}</option>
                        @foreach($categoryOptions as $value => $label)
                            <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                        @endforeach
                        <option value="{{ $otherCategoryValue }}" @selected($category === $otherCategoryValue)>{{ __('notify.clients.contact_model.category_other') }}</option>
                    </select>
                @else
                    <input id="business_category" class="touch-input" name="business_category" value="{{ $category }}" list="business-category-options" required maxlength="120" @error('business_category') aria-invalid="true" @enderror>
                    <datalist id="business-category-options">
                        @foreach($businessCategorySuggestions as $suggestion)
                            <option value="{{ $suggestion }}"></option>
                        @endforeach
                    </datalist>
                @endif
                @error('business_category') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            @if($categoryOptions !== [])
                <div class="field notify-conditional-field" data-intake-field="business_category_other" x-show="category === @js($otherCategoryValue)">
                    <label for="business_category_other">{{ __('notify.clients.contact_model.category_other_input') }} *</label>
                    <input id="business_category_other" class="touch-input" name="business_category_other" value="{{ old('business_category_other') }}" maxlength="120" @error('business_category_other') aria-invalid="true" @enderror>
                    @error('business_category_other') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="field" data-intake-field="city_area">
                <label for="city_area">{{ __('notify.clients.city_area') }} *</label>
                <input id="city_area" class="touch-input" name="city_area" value="{{ old('city_area', $client?->city_area) }}" list="city-area-options" required maxlength="120" autocomplete="address-level2" aria-describedby="city_area_hint" @error('city_area') aria-invalid="true" @enderror>
                <datalist id="city-area-options">
                    @foreach($cityAreaSuggestions as $suggestion)
                        <option value="{{ $suggestion }}"></option>
                    @endforeach
                </datalist>
                <small id="city_area_hint" class="notify-field-hint">{{ __('notify.clients.contact_model.city_area_hint') }}</small>
                @error('city_area') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="lead_source">
                <label for="lead_source">{{ __('notify.clients.lead_source') }} *</label>
                <select id="lead_source" class="touch-input" name="lead_source" required @error('lead_source') aria-invalid="true" @enderror>
                    <option value="">{{ __('notify.clients.choose_lead_source') }}</option>
                    @foreach($leadSourceOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('lead_source', $client?->lead_source) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('lead_source') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="phone">
                <label for="phone">{{ __('notify.clients.contact_model.primary_phone') }} *</label>
                <input id="phone" class="touch-input" type="tel" name="phone" value="{{ old('phone', $client?->phone) }}" required maxlength="50" inputmode="tel" autocomplete="tel" dir="ltr" @error('phone') aria-invalid="true" @enderror>
                @error('phone') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <fieldset class="field notify-conditional-field notify-phone-type" data-intake-field="primary_phone_type" @error('primary_phone_type') aria-invalid="true" @enderror>
                <legend>{{ __('notify.clients.contact_model.primary_phone_type') }} *</legend>
                <div class="notify-phone-type__options">
                    @foreach(\App\Models\Client::PRIMARY_PHONE_TYPES as $type)
                        <label class="notify-phone-type__option">
                            <input type="radio" name="primary_phone_type" value="{{ $type }}" x-model="phoneType" required @checked($phoneType === $type)>
                            <span>{{ __('notify.clients.contact_model.phone_types.'.$type) }}</span>
                        </label>
                    @endforeach
                </div>
                @error('primary_phone_type') <span class="error" role="alert">{{ $message }}</span> @enderror
            </fieldset>

            <div class="field" data-intake-field="business_phone" x-show="phoneType !== 'business'">
                <label for="business_phone">{{ __('notify.clients.contact_model.business_phone') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                <input id="business_phone" class="touch-input" type="tel" name="business_phone" value="{{ $businessPhone }}" maxlength="50" inputmode="tel" autocomplete="tel" dir="ltr" @error('business_phone') aria-invalid="true" @enderror>
                @error('business_phone') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="location_text">
                <label for="location_text">{{ __('notify.clients.contact_model.address') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                <input id="location_text" class="touch-input" name="location_text" value="{{ old('location_text', $client?->location_text) }}" maxlength="255" autocomplete="street-address" @error('location_text') aria-invalid="true" @enderror>
                @error('location_text') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>
        </div>
    </section>

    @php $contactFields = ['contact_name', 'contact_role', 'contact_phone', 'contact_whatsapp', 'contact_email']; @endphp
    <details class="notify-details notify-client-form__section" data-client-contact-section @if($errors->hasAny($contactFields) || $primary || filled($client?->contact_person)) open @endif>
        <summary>
            <span>{{ __('notify.clients.contact_model.contact_section') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></span>
            <small>{{ __('notify.clients.contact_model.contact_section_meta') }}</small>
        </summary>
        <div class="notify-form-grid notify-details__body">
            <div class="field" data-intake-field="contact_name">
                <label for="contact_name">{{ __('notify.clients.contact_model.contact_name') }}</label>
                <input id="contact_name" class="touch-input" name="contact_name" value="{{ old('contact_name', $primary?->name ?? $client?->contact_person) }}" maxlength="120" autocomplete="name" @error('contact_name') aria-invalid="true" @enderror>
                @error('contact_name') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="contact_role" x-show="phoneType === 'business' || phoneType === ''">
                <label for="contact_role">{{ __('notify.clients.contact_model.contact_role') }}</label>
                <select id="contact_role" class="touch-input" name="contact_role" @error('contact_role') aria-invalid="true" @enderror>
                    <option value="">{{ __('notify.clients.contact_model.contact_role_none') }}</option>
                    @foreach($contactRoleOptions as $value => $label)
                        <option value="{{ $value }}" @selected($contactRole === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('contact_role') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="contact_phone">
                <label for="contact_phone">
                    <span x-show="phoneType === 'business' || phoneType === ''">{{ __('notify.clients.contact_model.contact_phone') }}</span>
                    <span x-show="phoneType === 'owner' || phoneType === 'manager'" x-cloak>{{ __('notify.clients.contact_model.contact_other_phone') }}</span>
                </label>
                <input id="contact_phone" class="touch-input" type="tel" name="contact_phone" value="{{ $contactPhone }}" maxlength="50" inputmode="tel" dir="ltr" @error('contact_phone') aria-invalid="true" @enderror>
                @error('contact_phone') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="contact_whatsapp">
                <label for="contact_whatsapp">{{ __('notify.clients.contact_model.contact_whatsapp') }}</label>
                <input id="contact_whatsapp" class="touch-input" type="tel" name="contact_whatsapp" value="{{ old('contact_whatsapp', $primary?->whatsapp_number) }}" maxlength="50" inputmode="tel" dir="ltr" @error('contact_whatsapp') aria-invalid="true" @enderror>
                @error('contact_whatsapp') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field" data-intake-field="contact_email">
                <label for="contact_email">{{ __('notify.clients.contact_model.contact_email') }}</label>
                <input id="contact_email" class="touch-input" type="email" name="contact_email" value="{{ old('contact_email', $primary?->email) }}" maxlength="255" inputmode="email" autocomplete="email" dir="ltr" @error('contact_email') aria-invalid="true" @enderror>
                @error('contact_email') <span class="error" role="alert">{{ $message }}</span> @enderror
            </div>
        </div>
    </details>

    <details class="notify-details notify-client-form__section" @if($errors->hasAny(['referred_by_name', 'referral_note', 'referral_commission_percentage']) || filled($client?->referred_by_name) || filled($client?->referral_note)) open @endif>
        <summary>
            <span>{{ __('notify.clients.contact_model.referral_section') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></span>
        </summary>
        <div class="notify-form-grid notify-details__body">
            <div class="field">
                <label for="referred_by_name">{{ __('notify.clients.referred_by_name') }}</label>
                <input id="referred_by_name" class="touch-input" name="referred_by_name" value="{{ old('referred_by_name', $client?->referred_by_name) }}" maxlength="255">
            </div>
            <div class="field">
                <label for="referral_note">{{ __('notify.clients.referral_note') }}</label>
                <input id="referral_note" class="touch-input" name="referral_note" value="{{ old('referral_note', $client?->referral_note) }}" maxlength="1000">
            </div>
            @if($client && \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::EDIT_REFERRAL_COMMISSION))
                <div class="field">
                    <label for="referral_commission_percentage">{{ __('notify.clients.referral_commission_percentage') }}</label>
                    <input id="referral_commission_percentage" class="touch-input" name="referral_commission_percentage" type="number" inputmode="decimal" min="0" max="100" step="0.01" value="{{ old('referral_commission_percentage', $client->referral_commission_bps === null ? '' : $client->referral_commission_bps / 100) }}">
                </div>
            @endif
        </div>
    </details>
</div>
