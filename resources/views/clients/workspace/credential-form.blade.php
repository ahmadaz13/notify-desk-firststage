{{--
    Add/edit credential form. SECURITY: the password and note inputs are always rendered empty
    (never `value=`/old()); their field names are in dontFlash.
--}}
@php
    $isEdit = (bool) $credential;
    $formKey = $isEdit ? 'edit-'.$credential->id : ($system ? 'add-'.$system->id : 'add-other');
    $useOld = ! $isEdit && $errors->credentials->any() && ($system ? (int) old('product_id') === $system->id : true);
@endphp
<form method="POST"
      action="{{ $isEdit ? route('clients.credentials.update', [$client, $credential]) : route('clients.credentials.store', $client) }}"
      class="notify-credential__form" autocomplete="off">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="notify-form-grid">
        @if(! $isEdit && $system)
            <input type="hidden" name="product_id" value="{{ $system->id }}">
        @elseif(! $isEdit)
            <div class="field">
                <label for="credential-product-{{ $formKey }}">{{ __('notify.credentials.choose_system') }} *</label>
                <select id="credential-product-{{ $formKey }}" class="touch-input" name="product_id" required>
                    <option value="">{{ __('notify.credentials.choose_system') }}</option>
                    @foreach($addable as $option)
                        <option value="{{ $option->id }}" @selected($useOld && (int) old('product_id') === $option->id)>{{ $credentialService->systemName($option) }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="field">
            <label for="credential-url-{{ $formKey }}">{{ __('notify.credentials.login_url') }}</label>
            <input id="credential-url-{{ $formKey }}" class="touch-input" type="url" name="login_url" inputmode="url" dir="ltr" maxlength="500"
                   value="{{ $useOld ? old('login_url') : $credential?->login_url }}">
        </div>

        <div class="field">
            <label for="credential-username-{{ $formKey }}">{{ __('notify.credentials.username') }}</label>
            <input id="credential-username-{{ $formKey }}" class="touch-input" name="username" dir="ltr" maxlength="255" autocapitalize="off" spellcheck="false"
                   value="{{ $useOld ? old('username') : $credential?->username }}">
        </div>

        <div class="field">
            <label for="credential-secret-{{ $formKey }}">{{ __('notify.credentials.password') }} @unless($isEdit)*@endunless</label>
            <input id="credential-secret-{{ $formKey }}" class="touch-input" type="password" name="credential_secret" dir="ltr" maxlength="1000"
                   autocomplete="new-password" @unless($isEdit) required @endunless>
            @if($isEdit)<small class="notify-muted">{{ __('notify.credentials.password_keep_hint') }}</small>@endif
        </div>

        <div class="field notify-conditional-field">
            <label for="credential-note-{{ $formKey }}">{{ __('notify.credentials.note') }}</label>
            <textarea id="credential-note-{{ $formKey }}" name="credential_note" maxlength="2000" rows="2"></textarea>
            @if($isEdit && $credential->hasNote())
                <small class="notify-muted">{{ __('notify.credentials.note_keep_hint') }}</small>
                <label class="notify-check">
                    <input type="checkbox" name="clear_note" value="1">
                    <span>{{ __('notify.credentials.clear_note') }}</span>
                </label>
            @endif
        </div>
    </div>

    <div class="notify-form-footer">
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.credentials.save') }}</button>
    </div>
</form>
