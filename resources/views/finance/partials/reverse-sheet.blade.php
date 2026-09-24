{{-- Reversal with a reason (P12): the one pattern for reversing a posted money event. The reversal itself
     is posted by the existing service; nothing is edited or deleted. Expects: sheetId, title, subtitle,
     action, label, minlength (nullable). --}}
@php
    $old = \App\Support\FormState::oldFor($sheetId);
@endphp
@formscope($sheetId)
<x-notify.sheet :id="$sheetId" size="sm" :title="$title" :subtitle="$subtitle" :action="$action">
    <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
    <x-notify.form-field :label="$label" :for="$sheetId.'-reason'" name="reason" :required="true">
        <textarea id="{{ $sheetId }}-reason" class="notify-input" name="reason" required @if($minlength) minlength="{{ $minlength }}" @endif maxlength="1000" rows="2" @invalid('reason', $sheetId.'-reason')>{{ $old('reason') }}</textarea>
    </x-notify.form-field>
    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.ui.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--danger">{{ $title }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
