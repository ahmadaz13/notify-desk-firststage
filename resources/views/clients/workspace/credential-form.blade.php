{{--
    Add/edit credential sheet (P5 rules, P12 sheet). SECURITY: the password and note inputs are always
    rendered empty (never `value=`/old()); their field names are in dontFlash, so a validation reopen
    asks for the password again instead of recovering it. Only URL/username (non-secret) are restored.
--}}
@php
    $isEdit = (bool) $credential;
    $formKey = $isEdit ? 'edit-'.$credential->id : ($system ? 'add-'.$system->id : 'add-other');
    $sheetId = 'credential-'.$formKey;
    $old = \App\Support\FormState::oldFor($sheetId);
    $reopened = $errors->credentials->any() && old('_form') === $sheetId;
    $systemName = $isEdit ? $credentialService->systemName($credential->product) : ($system ? $credentialService->systemName($system) : null);
    $title = $isEdit ? __('notify.credentials.edit') : ($system ? __('notify.credentials.add') : __('notify.credentials.add_for_system'));
@endphp

@formscope($sheetId)
<x-notify.sheet :id="$sheetId" :title="$title" :subtitle="$systemName" error-bag="credentials"
    :class="$isEdit || $system ? 'notify-credential__sheet' : 'notify-credential__sheet notify-credential__add'"
    :action="$isEdit ? route('clients.credentials.update', [$client, $credential]) : route('clients.credentials.store', $client)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-attributes="['autocomplete' => 'off', 'data-credential-form' => $formKey]">

    @if(! $isEdit && $system)
        <input type="hidden" name="product_id" value="{{ $system->id }}">
    @elseif(! $isEdit)
        <x-notify.form-field :label="__('notify.credentials.choose_system')" :for="'credential-product-'.$formKey" name="product_id" bag="credentials" :required="true">
            <select id="credential-product-{{ $formKey }}" class="notify-input" name="product_id" required @invalid('product_id', 'credential-product-'.$formKey, 'credentials')>
                <option value="">{{ __('notify.credentials.choose_system') }}</option>
                @foreach($addable as $option)
                    <option value="{{ $option->id }}" @selected((int) $old('product_id') === $option->id)>{{ $credentialService->systemName($option) }}</option>
                @endforeach
            </select>
        </x-notify.form-field>
    @endif

    <x-notify.form-field :label="__('notify.credentials.login_url')" :for="'credential-url-'.$formKey" name="login_url" bag="credentials" :optional="true">
        <input id="credential-url-{{ $formKey }}" class="notify-input" type="url" name="login_url" inputmode="url" dir="ltr" maxlength="500"
               value="{{ $old('login_url', $credential?->login_url) }}" @invalid('login_url', 'credential-url-'.$formKey, 'credentials')>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.credentials.username')" :for="'credential-username-'.$formKey" name="username" bag="credentials" :optional="true">
        <input id="credential-username-{{ $formKey }}" class="notify-input" name="username" dir="ltr" maxlength="255" autocapitalize="off" spellcheck="false"
               value="{{ $old('username', $credential?->username) }}" @invalid('username', 'credential-username-'.$formKey, 'credentials')>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.credentials.password')" :for="'credential-secret-'.$formKey" name="credential_secret" bag="credentials" :required="! $isEdit"
        :hint="$reopened && ! $isEdit ? __('notify.ui.reenter_secret') : ($isEdit ? __('notify.credentials.password_keep_hint') : null)">
        <input id="credential-secret-{{ $formKey }}" class="notify-input" type="password" name="credential_secret" dir="ltr" maxlength="1000"
               autocomplete="new-password" @unless($isEdit) required @endunless
               @invalid('credential_secret', 'credential-secret-'.$formKey, 'credentials', true) @if($reopened && ! $isEdit) data-autofocus @endif>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.credentials.note')" :for="'credential-note-'.$formKey" name="credential_note" bag="credentials" :optional="true"
        :hint="$isEdit && $credential->hasNote() ? __('notify.credentials.note_keep_hint') : null">
        <textarea id="credential-note-{{ $formKey }}" class="notify-input" name="credential_note" maxlength="2000" rows="2"
                  @invalid('credential_note', 'credential-note-'.$formKey, 'credentials', $isEdit && $credential->hasNote())></textarea>
        @if($isEdit && $credential->hasNote())
            <label class="notify-choice-chip">
                <input type="checkbox" name="clear_note" value="1">
                <span>{{ __('notify.credentials.clear_note') }}</span>
            </label>
        @endif
    </x-notify.form-field>

    <x-slot:footer>
        @if($isEdit)
            <button type="submit" form="credential-delete-{{ $credential->id }}" class="notify-button notify-button--ghost notify-sheet__footer-start notify-text-danger">{{ __('notify.credentials.delete') }}</button>
        @endif
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.credentials.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.credentials.save') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope

@if($isEdit)
    {{-- Destructive action lives outside the edit form and is confirmed in the shared dialog. --}}
    <form id="credential-delete-{{ $credential->id }}" method="POST" action="{{ route('clients.credentials.destroy', [$client, $credential]) }}" class="notify-credential__delete"
          data-confirm="{{ __('notify.credentials.delete_confirm', ['system' => $systemName]) }}" data-confirm-label="{{ __('notify.credentials.delete') }}" hidden>
        @csrf
        @method('DELETE')
    </form>
@endif
