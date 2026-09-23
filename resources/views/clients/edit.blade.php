@extends('layouts.app')

@section('content')
@php
    $detailFields = ['source_reference', 'number_of_branches', 'city', 'area', 'instagram', 'website', 'maps_url', 'notes'];
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
        class="notify-prospect-form notify-client-form"
        x-init="$nextTick(() => { const field = $el.querySelector('[aria-invalid=true]'); if (field) { field.focus(); field.scrollIntoView({ block: 'center' }); } })"
    >
        @csrf
        @method('PUT')

        @include('clients.partials.form-core', ['client' => $client])

        <details class="notify-details notify-client-form__section" @if($errors->hasAny($detailFields)) open @endif>
            <summary>
                <span>{{ __('notify.clients.more_details') }}</span>
                <small>{{ __('notify.clients.more_details_meta') }}</small>
            </summary>
            <div class="notify-form-grid notify-details__body">
                <div class="field">
                    <label for="source_reference">{{ __('notify.clients.source_reference') }}</label>
                    <input id="source_reference" class="touch-input" name="source_reference" value="{{ old('source_reference', $client->source_reference) }}" maxlength="255" @error('source_reference') aria-invalid="true" @enderror>
                    @error('source_reference') <span class="error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="number_of_branches">{{ __('notify.clients.number_of_branches') }}</label>
                    <input id="number_of_branches" class="touch-input" type="number" inputmode="numeric" min="1" max="999" name="number_of_branches" value="{{ old('number_of_branches', $client->number_of_branches ?? 1) }}" @error('number_of_branches') aria-invalid="true" @enderror>
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

                <div class="field full">
                    <label for="maps_url">{{ __('notify.clients.maps_url') }}</label>
                    <input id="maps_url" class="touch-input" type="url" name="maps_url" value="{{ old('maps_url', $client->maps_url) }}" maxlength="500" dir="ltr" @error('maps_url') aria-invalid="true" @enderror>
                    @error('maps_url') <span class="error" role="alert">{{ $message }}</span> @enderror
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
