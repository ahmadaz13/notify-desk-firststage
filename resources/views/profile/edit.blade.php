@extends('layouts.app')

@section('content')
<div class="notify-page-head"><div><div class="eyebrow">{{ __('notify.navigation.account') }}</div><h1 class="page-title">{{ __('notify.profile.title') }}</h1></div></div>
<div class="notify-settings-grid">
    <section class="notify-settings-section">
        <h2>{{ __('notify.profile.personal_information') }}</h2>
        <form method="POST" action="{{ route('profile.update') }}" class="notify-form-grid">@csrf @method('PUT')
            <label class="notify-form-field"><span>{{ __('notify.profile.name') }}</span><input name="name" value="{{ old('name', $user->name) }}" required></label>
            <label class="notify-form-field"><span>{{ __('notify.profile.email') }}</span><input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
            <div class="notify-form-field--wide"><button class="notify-button notify-button--primary" type="submit">{{ __('notify.actions.save') }}</button></div>
        </form>
    </section>
    <section class="notify-settings-section" id="password">
        <h2>{{ __('notify.profile.change_password') }}</h2>
        <form method="POST" action="{{ route('profile.password') }}" class="notify-form-grid">@csrf @method('PUT')
            <label class="notify-form-field notify-form-field--wide"><span>{{ __('notify.profile.current_password') }}</span><input type="password" name="current_password" autocomplete="current-password" required></label>
            <label class="notify-form-field"><span>{{ __('notify.profile.new_password') }}</span><input type="password" name="password" autocomplete="new-password" required></label>
            <label class="notify-form-field"><span>{{ __('notify.profile.confirm_password') }}</span><input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <div class="notify-form-field--wide"><button class="notify-button notify-button--primary" type="submit">{{ __('notify.profile.update_password') }}</button></div>
        </form>
    </section>
</div>
@endsection
